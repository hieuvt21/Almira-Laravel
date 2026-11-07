<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Hạng khách hàng theo tổng chi tiêu, cấu hình ở "Hạng khách hàng" (menu Hệ thống).
 *
 * Module Bán hàng dùng:  $pct = CustomerTiers::discountFor($customer->total_spent);
 */
class CustomerTiers
{
    /** Biểu tượng gợi ý (PrimeIcons). Người dùng cũng có thể gõ tên biểu tượng PrimeIcons khác, dạng `pi-xxx`. */
    public const ICONS = [
        'pi-star-fill', 'pi-star', 'pi-user', 'pi-heart-fill', 'pi-bookmark-fill', 'pi-gift',
        'pi-bolt', 'pi-verified', 'pi-trophy', 'pi-shield', 'pi-flag-fill', 'pi-thumbs-up-fill',
        'pi-sun', 'pi-moon', 'pi-tag', 'pi-tags', 'pi-circle-fill', 'pi-users', 'pi-wallet', 'pi-money-bill',
        'pi-credit-card', 'pi-shopping-bag', 'pi-shopping-cart', 'pi-percentage', 'pi-bell', 'pi-face-smile',
        'pi-key', 'pi-briefcase', 'pi-building', 'pi-home', 'pi-megaphone', 'pi-box',
    ];

    /** Tên biểu tượng PrimeIcons hợp lệ, ví dụ `pi-star-fill`. */
    public const ICON_PATTERN = '/^pi-[a-z0-9]+(-[a-z0-9]+)*$/';

    /** Màu mặc định theo thứ tự hạng: thấp -> cao (khớp với TierBadge cũ). */
    public const COLORS = ['#64748b', '#0284c7', '#0d9488', '#a16207', '#ea580c', '#7c3aed', '#db2777', '#334155'];

    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'tiers' => [
                ['name' => 'Mới', 'min_spent' => 0, 'discount' => 0, 'color' => '#0d9488', 'icon' => 'pi-user'],
                ['name' => 'Bạc', 'min_spent' => 2000000, 'discount' => 1, 'color' => '#64748b', 'icon' => 'pi-shield'],
                ['name' => 'Vàng', 'min_spent' => 5000000, 'discount' => 2, 'color' => '#a16207', 'icon' => 'pi-star-fill'],
                ['name' => 'Kim cương', 'min_spent' => 10000000, 'discount' => 3, 'color' => '#0284c7', 'icon' => 'pi-verified'],
                ['name' => 'Vip', 'min_spent' => 30000000, 'discount' => 5, 'color' => '#7c3aed', 'icon' => 'pi-trophy'],
            ],
        ];
    }

    /** Cấu hình đang dùng: các hạng đã xếp tăng dần theo mức chi tiêu, kèm `level` (0, 1, 2...). */
    public static function config(): array
    {
        $defaults = self::defaults();
        $saved = Setting::json('customer_tiers', $defaults);
        $raw = is_array($saved['tiers'] ?? null) ? $saved['tiers'] : $defaults['tiers'];

        $tiers = collect($raw)
            ->map(fn ($t) => [
                'name' => trim((string) ($t['name'] ?? '')),
                'min_spent' => (float) ($t['min_spent'] ?? 0),
                'discount' => min(100.0, max(0.0, (float) ($t['discount'] ?? 0))),
                'color' => (string) ($t['color'] ?? ''),
                'icon' => (string) ($t['icon'] ?? ''),
            ])
            ->filter(fn ($t) => $t['name'] !== '')
            ->sortBy('min_spent')
            ->values()
            ->all();

        if (! $tiers) {
            $tiers = $defaults['tiers'];
        }

        $tiers[0]['min_spent'] = 0; // hạng đầu luôn bắt đầu từ 0

        foreach ($tiers as $i => &$tier) {
            $tier['level'] = $i;
            // Dữ liệu cũ chưa có màu/biểu tượng: dùng mặc định theo thứ tự hạng
            if (! preg_match('/^#[0-9a-fA-F]{6}$/', $tier['color'])) {
                $tier['color'] = self::COLORS[$i % count(self::COLORS)];
            }
            if (! preg_match(self::ICON_PATTERN, $tier['icon'])) {
                $tier['icon'] = 'pi-star-fill';
            }
        }
        unset($tier);

        return ['enabled' => (bool) ($saved['enabled'] ?? true), 'tiers' => $tiers];
    }

    public static function enabled(): bool
    {
        return self::config()['enabled'];
    }

    /** Hạng của khách theo tổng chi tiêu; null nếu tính năng đang tắt. */
    public static function for(float|int|string|null $spent): ?array
    {
        $cfg = self::config();
        if (! $cfg['enabled']) {
            return null;
        }

        $spent = (float) $spent;
        $tiers = $cfg['tiers'];
        $current = $tiers[0];

        foreach ($tiers as $tier) {
            if ($spent >= $tier['min_spent']) {
                $current = $tier;
            }
        }

        $next = $tiers[$current['level'] + 1] ?? null;

        return [
            'level' => $current['level'],
            'name' => $current['name'],
            'min_spent' => $current['min_spent'],
            'discount' => $current['discount'],
            'color' => $current['color'],
            'icon' => $current['icon'],
            'next' => $next ? [
                'name' => $next['name'],
                'min_spent' => $next['min_spent'],
                'remaining' => max(0, $next['min_spent'] - $spent),
            ] : null,
        ];
    }

    /** % giảm giá theo hạng (0 nếu tắt). */
    public static function discountFor(float|int|string|null $spent): float
    {
        return (float) (self::for($spent)['discount'] ?? 0);
    }

    /** Khoảng chi tiêu [từ, đến) của một hạng; đến = null nếu là hạng cao nhất. */
    public static function range(int $level): ?array
    {
        $cfg = self::config();
        if (! $cfg['enabled'] || ! isset($cfg['tiers'][$level])) {
            return null;
        }

        return [$cfg['tiers'][$level]['min_spent'], $cfg['tiers'][$level + 1]['min_spent'] ?? null];
    }
}
