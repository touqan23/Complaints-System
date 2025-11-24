<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ComplaintPolicy
{
    /**
     * Determine whether the user can view any models.
//     */
//    public function viewAny(User $user): bool
//    {
//        //
//    }
//
//    /**
//     * Determine whether the user can view the model.
//     */
//    public function view(User $user, Complaint $complaint): bool
//    {
//        //
//    }
//
//    /**
//     * Determine whether the user can create models.
//     */
//    public function create(User $user): bool
//    {
//        //
//    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Complaint $complaint)
    {
        // الشرط الأساسي: المواطن هو صاحب الشكوى
        if ($complaint->citizen_id !== $user->id) {
            return false;
        }

        // السماح بالتعديل فقط بحالة need_more_info
        return $complaint->status === 'need_more_info';
    }


//    /**
//     * Determine whether the user can delete the model.
//     */
//    public function delete(User $user, Complaint $complaint): bool
//    {
//        //
//    }
//
//    /**
//     * Determine whether the user can restore the model.
//     */
//    public function restore(User $user, Complaint $complaint): bool
//    {
//        //
//    }
//
//    /**
//     * Determine whether the user can permanently delete the model.
//     */
//    public function forceDelete(User $user, Complaint $complaint): bool
//    {
//        //
//    }
}
