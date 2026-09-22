<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\SearchServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        protected SearchServiceInterface $searchService
    ) {}

    /**
     * تنفيذ البحث السريع الشامل عبر فهارس النظام
     */
    public function globalSearch(Request $request): JsonResponse
    {
        $query = (string) $request->input('q', $request->input('query', ''));
        $results = $this->searchService->search($query);

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }
}
