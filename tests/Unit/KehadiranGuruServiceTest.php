<?php

namespace Tests\Unit;

use App\Services\KehadiranGuruService;
use PHPUnit\Framework\TestCase;

class KehadiranGuruServiceTest extends TestCase
{
    public function test_journal_marks_scheduled_teacher_as_present(): void
    {
        $this->assertSame(
            'Hadir',
            (new KehadiranGuruService)->tentukanStatus(true, false)
        );
    }

    public function test_unfinished_schedule_waits_for_the_end_time(): void
    {
        $this->assertSame(
            'Menunggu',
            (new KehadiranGuruService)->tentukanStatus(false, false)
        );
    }

    public function test_finished_schedule_without_journal_is_unexplained(): void
    {
        $this->assertSame(
            'Tanpa Keterangan',
            (new KehadiranGuruService)->tentukanStatus(false, true)
        );
    }

    public function test_official_leave_overrides_missing_journal(): void
    {
        $this->assertSame(
            'Sakit',
            (new KehadiranGuruService)->tentukanStatus(false, true, 'Sakit')
        );
    }
}
