<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use DTApi\Helpers\TeHelper;

class TeHelperTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_willExpireAt()
    {
       $due_time = '2023-01-11';
       $created_time = '2023-01-09';
       $time = TeHelper::willExpireAt($due_time,$created_time);
       $this->assertEquals($time);
    }
}