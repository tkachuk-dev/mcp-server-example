<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Analytics;

use App\Enums\RfmSegment;
use App\Repositories\CustomerMetricsRepository;
use App\Services\Analytics\RfmSegmentationService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RfmSegmentationServiceTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, RfmSegment}>
     */
    public static function segments(): iterable
    {
        yield 'champion' => [30, 5, RfmSegment::Champions];
        yield 'recent but rare is not champion' => [30, 4, RfmSegment::Loyal];
        yield 'loyal' => [60, 3, RfmSegment::Loyal];
        yield 'potential' => [60, 1, RfmSegment::Potential];
        yield 'at risk lower bound' => [61, 10, RfmSegment::AtRisk];
        yield 'at risk upper bound' => [120, 1, RfmSegment::AtRisk];
        yield 'hibernating' => [240, 1, RfmSegment::Hibernating];
        yield 'lost' => [241, 50, RfmSegment::Lost];
    }

    #[DataProvider('segments')]
    public function test_segment_boundaries(int $recencyDays, int $frequency, RfmSegment $expected): void
    {
        $this->assertSame($expected, $this->service()->segmentFor($recencyDays, $frequency));
    }

    public function test_recency_counts_calendar_days(): void
    {
        $now = CarbonImmutable::parse('2026-10-13 00:30:00', 'UTC');

        $this->assertSame(1, $this->service()->recencyDays(CarbonImmutable::parse('2026-10-12 23:59:00', 'UTC'), $now));
        $this->assertSame(0, $this->service()->recencyDays(CarbonImmutable::parse('2026-10-13 00:10:00', 'UTC'), $now));
    }

    private function service(): RfmSegmentationService
    {
        return new RfmSegmentationService(new CustomerMetricsRepository);
    }
}
