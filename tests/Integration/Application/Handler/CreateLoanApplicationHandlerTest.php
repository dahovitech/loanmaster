<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Handler;

use App\Application\Command\Loan\CreateLoanApplicationCommand;
use App\Application\Handler\Loan\CreateLoanApplicationHandler;
use App\Domain\Entity\Loan;
use App\Domain\Entity\User;
use App\Domain\Repository\LoanRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\EventBusInterface;
use App\Domain\Service\LoanNumberGeneratorInterface;
use App\Domain\ValueObject\LoanId;
use App\Domain\ValueObject\LoanStatus;
use App\Domain\ValueObject\UserId;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreateLoanApplicationHandlerTest extends TestCase
{
    private LoanRepositoryInterface|MockObject $loanRepository;
    private UserRepositoryInterface|MockObject $userRepository;
    private EventBusInterface|MockObject $eventBus;
    private LoanNumberGeneratorInterface|MockObject $numberGenerator;
    private CreateLoanApplicationHandler $handler;
    
    protected function setUp(): void
    {
        $this->loanRepository = $this->createMock(LoanRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->eventBus = $this->createMock(EventBusInterface::class);
        $this->numberGenerator = $this->createMock(LoanNumberGeneratorInterface::class);
        
        $this->handler = new CreateLoanApplicationHandler(
            $this->loanRepository,
            $this->userRepository,
            $this->eventBus,
            $this->numberGenerator
        );
    }
    
    public function testHandleCreateLoanApplication(): void
    {
        $userId = UserId::generate();
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($userId);
        
        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);
        
        $this->numberGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('DOC000123456');
        
        $this->loanRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Loan $loan) {
                return $loan->getStatus() === LoanStatus::PENDING
                    && $loan->getNumber() === 'DOC000123456'
                    && $loan->getAmount()->getValue() === 10000.0;
            }));
        
        $this->eventBus
            ->expects($this->once())
            ->method('dispatchEvents')
            ->with($this->isType('array'));
        
        $command = new CreateLoanApplicationCommand(
            $userId->toString(),
            'personal',
            10000.0,
            24,
            'Test loan'
        );
        
        $result = ($this->handler)($command);
        
        $this->assertInstanceOf(LoanId::class, $result);
    }
    
    public function testHandleWithNonExistentUser(): void
    {
        $userId = UserId::generate();
        
        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn(null);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User not found');
        
        $command = new CreateLoanApplicationCommand(
            $userId->toString(),
            'personal',
            10000.0,
            24
        );
        
        ($this->handler)($command);
    }
    
    public function testHandleWithInvalidLoanType(): void
    {
        $userId = UserId::generate();
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($userId);
        
        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);
        
        $this->expectException(\ValueError::class);
        
        $command = new CreateLoanApplicationCommand(
            $userId->toString(),
            'invalid_type',
            10000.0,
            24
        );
        
        ($this->handler)($command);
    }
}
