<?php

namespace App\Rules;

use App\Models\Scopes\BranchScope;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A branch-confined user (see BranchScope) may only submit their own branch id.
 * Unrestricted users (super-admin, no branch, guests/console) pass any branch.
 */
class WithinUserBranch implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $restricted = BranchScope::restrictedBranchId();

        if ($restricted !== null && $value !== null && $value !== '' && (int) $value !== $restricted) {
            $fail('لا يمكنك تنفيذ هذه العملية على فرع غير فرعك.');
        }
    }
}
