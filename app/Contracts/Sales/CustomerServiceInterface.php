<?php

namespace App\Contracts\Sales;

use App\Models\Customer;
use App\Models\CustomerVehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CustomerServiceInterface
{
    public function getPaginatedCustomers(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function getCustomerStats(): array;

    public function search(string $term, int $limit = 20): Collection;

    public function getCustomerProfile(Customer $customer): array;

    public function createCustomer(array $data): Customer;

    public function updateCustomer(Customer $customer, array $data): Customer;

    public function deleteCustomer(Customer $customer): void;

    public function addVehicle(Customer $customer, array $data): CustomerVehicle;

    public function updateVehicle(CustomerVehicle $vehicle, array $data): CustomerVehicle;

    public function deleteVehicle(CustomerVehicle $vehicle): void;
}
