<?php

declare(strict_types=1);

namespace App\Application\Order\Command\AttachCustomerToDraft;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Customer\Repository\CustomerRepositoryInterface;
use App\Domain\Order\Repository\OrderRepositoryInterface;

final readonly class AttachCustomerToDraftHandler
{
    public function __construct(
        private TransactionManagerInterface $transactionManager,
        private OrderRepositoryInterface $orderRepository,
        private CustomerRepositoryInterface $customerRepository,
    ) {
    }

    public function __invoke(
        AttachCustomerToDraftInput $input,
    ): AttachCustomerToDraftResult {
        if ($input->orderId <= 0) {
            throw new \InvalidArgumentException(
                'Order ID must be greater than zero.',
            );
        }

        if ($input->customerId <= 0) {
            throw new \InvalidArgumentException(
                'Customer ID must be greater than zero.',
            );
        }

        return $this->transactionManager->run(
            function (
                TransactionContextInterface $transaction,
            ) use ($input): AttachCustomerToDraftResult {
                $order = $this->orderRepository->findById(
                    $input->orderId,
                );

                if ($order === null) {
                    throw new \DomainException(
                        sprintf(
                            'Order %d was not found.',
                            $input->orderId,
                        ),
                    );
                }

                if (!$order->isDraft()) {
                    throw new \DomainException(
                        'Customer can only be changed while order is draft.',
                    );
                }

                $customer = $this->customerRepository->findById(
                    $input->customerId,
                );

                if ($customer === null) {
                    throw new \DomainException(
                        sprintf(
                            'Customer %d was not found.',
                            $input->customerId,
                        ),
                    );
                }

                $order->changeCustomer($customer);

                $this->orderRepository->save($order);
                $transaction->flush();

                $orderId = $order->getId();

                if ($orderId === null) {
                    throw new \LogicException(
                        'Order ID was not generated after flush.',
                    );
                }

                $customerId = $customer->getId();

                if ($customerId === null) {
                    throw new \LogicException(
                        'Customer ID was not generated after flush.',
                    );
                }

                return new AttachCustomerToDraftResult(
                    orderId: $orderId,
                    orderNumber: $order
                        ->getOrderNumber()
                        ->value(),
                    status: $order->getStatus(),
                    customerId: $customerId,
                    customerName: $customer->getName(),
                    customerPhone: $customer->getPhone(),
                );
            },
        );
    }
}
