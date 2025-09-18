<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\LoanId;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class LoanIdTest extends TestCase
{
    public function testGenerate(): void
    {
        $id = LoanId::generate();
        
        $this->assertInstanceOf(LoanId::class, $id);
        $this->assertTrue(Uuid::isValid($id->toString()));
    }
    
    public function testFromString(): void
    {
        $uuidString = '123e4567-e89b-12d3-a456-426614174000';
        $id = LoanId::fromString($uuidString);
        
        $this->assertEquals($uuidString, $id->toString());
    }
    
    public function testToString(): void
    {
        $uuidString = '123e4567-e89b-12d3-a456-426614174000';
        $id = LoanId::fromString($uuidString);
        
        $this->assertEquals($uuidString, (string) $id);
        $this->assertEquals($uuidString, $id->__toString());
    }
    
    public function testEquals(): void
    {
        $uuidString = '123e4567-e89b-12d3-a456-426614174000';
        $id1 = LoanId::fromString($uuidString);
        $id2 = LoanId::fromString($uuidString);
        $id3 = LoanId::generate();
        
        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }
    
    public function testGeneratedIdsAreUnique(): void
    {
        $id1 = LoanId::generate();
        $id2 = LoanId::generate();
        
        $this->assertFalse($id1->equals($id2));
        $this->assertNotEquals($id1->toString(), $id2->toString());
    }
}
