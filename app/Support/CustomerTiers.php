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
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'tiers' => [
                ['name' => 'Thành viên', 'min_spent' => 0, 'discount' => 0],
                ['name' => 'Thân thiết', 'min_spent' => 2000000, 'discount' => 1],
                ['name' => 'Bạc', 'min_spent' => 5000000, 'discount' => 2],
                ['name' => 'Vàng', 'min_spent' => 10000000, 'discount' => 3],
                ['name' => 'VIP', 'min_spent' => 30000000, 'discount' => 5],
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
                'discount' => (float) ($t['discount'] ?? 0),
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
