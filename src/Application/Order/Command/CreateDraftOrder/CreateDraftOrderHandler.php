<?php

declare(strict_types=1);

namespace App\Application\Order\Command\CreateDraftOrder;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Order\OrderNumberGeneratorInterface;
use App\Domain\Order\Order;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Customer\Repository\CustomerRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final class CreateDraftOrderHandler
{
    public function __construct(
        private readonly TransactionManagerInterface $transactionManager,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly OrderNumberGeneratorInterface $orderNumberGenerator,
    ) {
    }

    public function __invoke(
        CreateDraftOrderInput $input,
    ): CreateDraftOrderResult {
        return $this->transactionManager->run(
            function (TransactionContextInterface $context) use ($input): CreateDraftOrderResult {
                $user = $this->userRepository->findById($input->userId);

                if ($user === null) {
                    throw new \DomainException('User not found.');
                }

                if (!$user->isActive()) {
                    throw new \DomainException('User is inactive.');
                }

                $customer = null;

                if ($input->customerId !== null) {
                    $customer = $this->customerRepository->findById(
                        $input->customerId,
                    );

                    if ($customer === null) {
                        throw new \DomainException('Customer not found.');
                    }
                }

                $order = new Order(
                    orderNumber: $this->generateUniqueOrderNumber(),
                    user: $user,
                    customer: $customer,
                    note: $input->note,
                );

                $this->orderRepository->save($order);

                $context->flush();

                return new CreateDraftOrderResult(
                    orderId: $order->getId() ?? throw new \LogicException(
                        'Order ID was not generated after flush.',
                    ),
                    orderNumber: $order->getOrderNumber()->value(),
                    status: $order->getStatus()->value,
                    subtotal: $order->getSubtotal()->toDecimal(),
                    total: $order->getTotal()->toDecimal(),
                );
            },
        );
    }

    private function generateUniqueOrderNumber(): OrderNumber
    {
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $orderNumber = $this->orderNumberGenerator->generate();

            if (!$this->orderRepository->existsByOrderNumber($orderNumber)) {
                return $orderNumber;
            }
        }

        throw new \RuntimeException(
            'Unable to generate a unique order number.',
        );
    }
}
