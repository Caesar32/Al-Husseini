<?php

namespace App\Contracts;

interface SearchServiceInterface
{
    /**
     * تنفيذ بحث فوري شامل في فهارس النظام (موظفون، عملاء، مركبات، بطاريات، فواتير، ضمانات، موردون)
     *
     * @param string $query
     * @param int $limitPerSection
     * @param list<string>|null $sections Sections to search (keys of SearchService::SECTION_PERMISSIONS); null = all
     * @return array
     */
    public function search(string $query, int $limitPerSection = 5, ?array $sections = null): array;
}
