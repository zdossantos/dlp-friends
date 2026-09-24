<?php

namespace App\Data;

use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementMetric;

final readonly class PartnerAnnouncementStatisticsData
{
    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     status: string,
     *     prepared: int,
     *     delivered: int,
     *     read: int,
     *     dismissed: int,
     *     unique_clicks: int,
     *     total_clicks: int,
     *     read_rate: float,
     *     dismiss_rate: float,
     *     unique_click_rate: float,
     *     pending?: int,
     *     failed?: int,
     *     skipped?: int
     * }
     */
    public static function from(
        PartnerAnnouncement $announcement,
        bool $includeOperations = false,
    ): array {
        $metric = $announcement->metric;
        $delivered = self::count($metric, 'delivered_count');

        $statistics = [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'status' => $announcement->status->value,
            'prepared' => self::count($metric, 'prepared_count'),
            'delivered' => $delivered,
            'read' => self::count($metric, 'read_count'),
            'dismissed' => self::count($metric, 'dismissed_count'),
            'unique_clicks' => self::count($metric, 'unique_click_count'),
            'total_clicks' => self::count($metric, 'total_click_count'),
            'read_rate' => self::rate(self::count($metric, 'read_count'), $delivered),
            'dismiss_rate' => self::rate(self::count($metric, 'dismissed_count'), $delivered),
            'unique_click_rate' => self::rate(self::count($metric, 'unique_click_count'), $delivered),
        ];

        if (! $includeOperations) {
            return $statistics;
        }

        return [
            ...$statistics,
            'pending' => (int) $announcement->getAttribute('pending_count'),
            'failed' => (int) $announcement->getAttribute('failed_count'),
            'skipped' => (int) $announcement->getAttribute('skipped_count'),
        ];
    }

    private static function rate(int $numerator, int $delivered): float
    {
        if ($delivered === 0) {
            return 0.0;
        }

        return round($numerator / $delivered * 100, 1);
    }

    private static function count(?PartnerAnnouncementMetric $metric, string $attribute): int
    {
        return (int) ($metric?->getAttribute($attribute) ?? 0);
    }
}
