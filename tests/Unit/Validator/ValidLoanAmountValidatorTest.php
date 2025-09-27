<?php

namespace App\Tests\Unit\Validator;

use App\Validator\ValidLoanAmount;
use App\Validator\ValidLoanAmountValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class ValidLoanAmountValidatorTest extends TestCase
{
    private ValidLoanAmountValidator $validator;
    private ExecutionContextInterface $context;

    protected function setUp(): void
    {
        $this->validator = new ValidLoanAmountValidator();
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->validator->initialize($this->context);
    }

    public function testValidateWithNullValue(): void
    {
        $constraint = new ValidLoanAmount();
        
        $this->context->expects($this->never())->method('buildViolation');
        
        $this->validator->validate(null, $constraint);
    }

    public function testValidateWithEmptyString(): void
    {
        $constraint = new ValidLoanAmount();
        
        $this->context->expects($this->never())->method('buildViolation');
        
        $this->validator->validate('', $constraint);
    }

    public function testValidateWithValidAmount(): void
    {
        $constraint = new ValidLoanAmount(min: 100, max: 10000);
        
        $this->context->expects($this->never())->method('buildViolation');
        
        $this->validator->validate(5000, $constraint);
    }

    public function testValidateWithAmountTooLow(): void
    {
        $constraint = new ValidLoanAmount(min: 100, max: 10000);
        
        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->method('setParameter')->willReturnSelf();
        $violationBuilder->expects($this->once())->method('addViolation');
        
        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($violationBuilder);
        
        $this->validator->validate(50, $constraint);
    }

    public function testValidateWithAmountTooHigh(): void
    {
        $constraint = new ValidLoanAmount(min: 100, max: 10000);
        
        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->method('setParameter')->willReturnSelf();
        $violationBuilder->expects($this->once())->method('addViolation');
        
        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($violationBuilder);
        
        $this->validator->validate(15000, $constraint);
    }

    public function testValidateWithStringAmount(): void
    {
        $constraint = new ValidLoanAmount(min: 100, max: 10000);
        
        $this->context->expects($this->never())->method('buildViolation');
        
        $this->validator->validate('5000.50', $constraint);
    }

    public function testValidateWithNonNumericValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $constraint = new ValidLoanAmount();
        
        $this->validator->validate('not-a-number', $constraint);
    }

    public function testConstraintWithCustomParameters(): void
    {
        $constraint = new ValidLoanAmount(
            min: 500,
            max: 50000,
            currency: 'USD'
        );
        
        $this->assertEquals(500, $constraint->min);
        $this->assertEquals(50000, $constraint->max);
        $this->assertEquals('USD', $constraint->currency);
    }
}