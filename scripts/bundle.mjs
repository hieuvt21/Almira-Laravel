#!/usr/bin/env node
// Gom file theo module để gửi cho AI (Claude web/app).
//
//   node scripts/bundle.mjs pos                 gom module pos + các module nó phụ thuộc + core
//   node scripts/bundle.mjs products customers  gom nhiều module
//   node scripts/bundle.mjs pos --list          chỉ liệt kê file và ước lượng token, không ghi file
//   node scripts/bundle.mjs pos --no-deps       chỉ module được chọn (+ core), bỏ phụ thuộc
//   node scripts/bundle.mjs pos --exclude shared-ui,css   bỏ bớt module (ví dụ đã có sẵn trong Project)
//   node scripts/bundle.mjs pos --out tmp/pos.md          đổi nơi ghi (mặc định: bundles/bundle-<tên>.md)
//   node scripts/bundle.mjs --modules           liệt kê các module trong bản đồ
//
// Bản đồ module nằm ở docs/module-map.json.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const mapFile = path.join(root, 'docs', 'module-map.json');
const map = JSON.parse(fs.readFileSync(mapFile, 'utf8'));
const modules = map.modules;

// ---- đọc tham số ----
const argv = process.argv.slice(2);
const names = [];
const opt = { list: false, deps: true, exclude: new Set(), out: null, showModules: false };
for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--list') opt.list = true;
    else if (a === '--no-deps') opt.deps = false;
    else if (a === '--modules') opt.showModules = true;
    else if (a === '--exclude') (argv[++i] || '').split(',').filter(Boolean).forEach((m) => opt.exclude.add(m));
    else if (a === '--out') opt.out = argv[++i];
    else if (a.startsWith('--')) fail(`Tham số lạ: ${a}`);
    else names.push(a);
}

function fail(msg) {
    console.error(`Lỗi: ${msg}`);
    process.exit(1);
}

if (opt.showModules) {
    for (const [name, m] of Object.entries(modules)) {
        const dep = m.depends_on?.length ? `  (cần: ${m.depends_on.join(', ')})` : '';
        console.log(`${name.padEnd(16)} ${m.mo_ta}${dep}`);
    }
    process.exit(0);
}

if (names.length === 0) fail('Chưa chọn module. Ví dụ: node scripts/bundle.mjs pos   (xem danh sách: --modules)');
for (const n of names) if (!modules[n]) fail(`Không có module "${n}". Chạy --modules để xem danh sách.`);

// ---- giải phụ thuộc (đệ quy) ----
const selected = [];
const seen = new Set();
function add(name) {
    if (seen.has(name)) return;
    seen.add(name);
    if (!modules[name]) fail(`Module "${name}" (trong depends_on) không tồn tại.`);
    if (opt.deps) (modules[name].depends_on || []).forEach(add);
    selected.push(name);
}
(map.always || []).forEach(add);
names.forEach(add);
const finalModules = selected.filter((m) => !opt.exclude.has(m));

// ---- glob đơn giản: ** (nhiều cấp), * (trong một cấp), ? ----
function globToRegex(glob) {
    let re = '';
    for (let i = 0; i < glob.length; i++) {
        const c = glob[i];
        if (c === '*') {
            if (glob[i + 1] === '*') {
                re += '.*';
                i++;
                if (glob[i + 1] === '/') i++;
            } else re += '[^/]*';
        } else if (c === '?') re += '[^/]';
        else re += c.replace(/[.+^${}()|[\]\\]/g, '\\$&');
    }
    return new RegExp(`^${re}$`);
}

const SKIP_DIRS = new Set(['vendor', 'node_modules', 'public', 'storage', '.git', 'bundles', 'bootstrap/cache']);
function walk(dir, out = []) {
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        const rel = path.relative(root, path.join(dir, e.name)).split(path.sep).join('/');
        if (e.isDirectory()) {
            if (!SKIP_DIRS.has(e.name) && !SKIP_DIRS.has(rel)) walk(path.join(dir, e.name), out);
        } else out.push(rel);
    }
    return out;
}
const allFiles = walk(root);

// ---- gom file (mỗi file chỉ xuất hiện một lần, giữ thứ tự module) ----
const files = [];
const owner = new Map();
const warnings = [];
for (const name of finalModules) {
    for (const pattern of modules[name].files || []) {
        const hasGlob = /[*?]/.test(pattern);
        const hits = hasGlob ? allFiles.filter((f) => globToRegex(pattern).test(f)) : allFiles.filter((f) => f === pattern);
        if (hits.length === 0) warnings.push(`[${name}] không tìm thấy: ${pattern}`);
        for (const f of hits.sort()) {
            if (!owner.has(f)) {
                owner.set(f, name);
                files.push(f);
            }
        }
    }
}

// ---- thống kê ----
const sizeOf = (f) => fs.statSync(path.join(root, f)).size;
const total = files.reduce((s, f) => s + sizeOf(f), 0);
const estTokens = Math.round(total / 3.5); // ước lượng thô cho code + tiếng Việt

console.log(`Module: ${finalModules.join(', ')}`);
if (opt.exclude.size) console.log(`Đã bỏ: ${[...opt.exclude].join(', ')}`);
console.log(`${files.length} file, ${(total / 1024).toFixed(1)} KB, khoảng ${estTokens.toLocaleString('vi-VN')} token (ước lượng thô)`);
warnings.forEach((w) => console.warn(`Cảnh báo: ${w}`));

if (opt.list) {
    for (const f of files) console.log(`${(sizeOf(f) / 1024).toFixed(1).padStart(6)} KB  ${f}  [${owner.get(f)}]`);
    process.exit(0);
}

// ---- ghi file gộp ----
const langOf = (f) => ({ '.php': 'php', '.vue': 'vue', '.js': 'js', '.mjs': 'js', '.json': 'json', '.css': 'css', '.md': 'md' }[path.extname(f)] || '');
let out = `# Gói file Almira: ${names.join(' + ')}\n\n`;
out += `Module gồm: ${finalModules.join(', ')}\n`;
out += `Tạo lúc: ${new Date().toISOString()}\n\n`;
out += `## Danh sách file\n`;
for (const f of files) out += `- ${f}\n`;
out += `\nMỗi file bên dưới bắt đầu bằng dòng \`=== FILE: <đường dẫn> ===\`. Khi trả file đã sửa, giữ nguyên đường dẫn này.\n`;
for (const f of files) {
    const body = fs.readFileSync(path.join(root, f), 'utf8').replace(/\r\n/g, '\n');
    const fence = body.includes('```') ? '````' : '```';
    out += `\n=== FILE: ${f} ===\n${fence}${langOf(f)}\n${body.replace(/\n$/, '')}\n${fence}\n`;
}

const outFile = opt.out
    ? path.resolve(process.cwd(), opt.out)
    : path.join(root, 'bundles', `bundle-${names.join('-')}.md`);
fs.mkdirSync(path.dirname(outFile), { recursive: true });
fs.writeFileSync(outFile, out);
console.log(`Đã ghi: ${path.relative(process.cwd(), outFile) || outFile}`);
