<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MemberApiController extends Controller
{
    /**
     * Display a listing of members.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Member::with('branchOffice:id,name');

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('member_no', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $members = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $members,
        ]);
    }

    /**
     * Display a specific member by Member No / IPI No or ID.
     */
    public function show(string $identifier): JsonResponse
    {
        $member = Member::with(['branchOffice', 'loanApplications'])
            ->where('member_no', $identifier)
            ->orWhere('id', $identifier)
            ->first();

        if (! $member) {
            return response()->json([
                'status' => 'error',
                'message' => 'Member record not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $member,
        ]);
    }

    /**
     * Verify if a Member No is valid and active.
     */
    public function verify(string $member_no): JsonResponse
    {
        $member = Member::with('branchOffice:id,name')
            ->where('member_no', $member_no)
            ->first();

        if (! $member) {
            return response()->json([
                'status' => 'error',
                'valid' => false,
                'message' => 'Invalid Member No. Record not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'valid' => true,
            'is_active' => (bool) $member->is_active,
            'member' => [
                'id' => $member->id,
                'member_no' => $member->member_no,
                'full_name' => $member->full_name,
                'email' => $member->email,
                'phone' => $member->phone,
                'branch' => $member->branchOffice?->name,
            ],
        ]);
    }

    /**
     * Store a newly created member via API.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'suffix' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:members,email',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'sex' => 'nullable|string|in:Male,Female',
            'civil_status' => 'nullable|string',
            'branch_office_id' => 'nullable|exists:branch_offices,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Generate sequential API-ready Member No (IPI-YYYY-XXXXX)
        $year = date('Y');
        $nextId = (Member::max('id') ?? 0) + 1;
        $memberNo = 'API-' . $year . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);

        $member = Member::create(array_merge(
            $validator->validated(),
            [
                'member_no' => $memberNo,
                'is_active' => true,
            ]
        ));

        return response()->json([
            'status' => 'success',
            'message' => 'Member created successfully.',
            'data' => $member->load('branchOffice'),
        ], 201);
    }
}
