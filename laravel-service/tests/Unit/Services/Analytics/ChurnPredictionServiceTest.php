<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Analytics;

use App\Repositories\CustomerMetricsRepository;
use App\Services\Analytics\ChurnPredictionService;
use App\Services\Analytics\RfmSegmentationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChurnPredictionServiceTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, float}>
     */
    public static function scores(): iterable
    {
        yield 'fresh and frequent' => [0, 10, 0.0];
        yield 'fresh and rare' => [0, 0, 0.3];
        yield 'half horizon' => [90, 1, 0.62];
        yield 'horizon is clamped' => [1_000, 1, 0.97];
        yield 'frequency is capped' => [180, 50, 0.7];
    }

    #[DataProvider('scores')]
    public function test_score_formula(int $recencyDays, int $frequency, float $expected): void
    {
        $repository = new CustomerMetricsRepository;
        $service = new ChurnPredictionService($repository, new RfmSegmentationService($repository));

        $this->assertSame($expected, $service->scoreFor($recencyDays, $frequency));
    }
}
