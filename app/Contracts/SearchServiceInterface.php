<?php

namespace App\Contracts;

interface SearchServiceInterface
{
    /**
     * تنفيذ بحث فوري شامل في فهارس النظام (موظفون، عملاء، مركبات، بطاريات، فواتير، ضمانات، موردون)
     *
     * @param string $query
     * @param int $limitPerSection
     * @return array
     */
    public function search(string $query, int $limitPerSection = 5): array;
}
