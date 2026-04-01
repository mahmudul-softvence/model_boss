<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserNameFormattingTest extends TestCase
{
    public function test_it_splits_a_full_name_into_first_middle_and_last_parts(): void
    {
        $parts = User::splitFullName('John Michael Doe');

        $this->assertSame('John', $parts['first_name']);
        $this->assertSame('Michael', $parts['middle_name']);
        $this->assertSame('Doe', $parts['last_name']);
    }

    public function test_it_formats_a_full_name_without_empty_parts(): void
    {
        $fullName = User::formatFullName('Jane', null, 'Doe');

        $this->assertSame('Jane Doe', $fullName);
    }
}
