<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminMutation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CoinAdjustmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['uid' => ['nullable', 'string'], 'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])], 'page' => ['integer', 'min:1'], 'per_page' => ['integer', 'min:1', 'max:100']]);
        $query = DB::table('coin_adjustments');
        foreach (['uid', 'status'] as $key) {
            if (! empty($data[$key])) {
                $query->where($key, $data[$key]);
            }
        }

        return response()->json(['data' => $query->orderByDesc('created_at')->orderBy('id')->paginate($data['per_page'] ?? 10)->through(fn (object $row): array => $this->profile($request, $row))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['uid' => ['required', 'string'], 'currency' => ['required', Rule::in(['member_coins', 'bonus'])], 'amount' => ['required', 'integer', 'not_in:0', 'between:-'.config('wallet.adjustment_limit').','.config('wallet.adjustment_limit')], 'reason' => ['required', 'string', 'min:1', 'max:500']]);
        $result = AdminMutation::run($request, 'coin.create', function () use ($request, $data): array {
            $user = User::where('uid', $data['uid'])->lockForUpdate()->first();
            abort_unless($user, 422, '用户不存在');
            abort_unless($user->status === 'active', 422, '用户状态不允许调整');
            $id = DB::table('coin_adjustments')->insertGetId($data + ['user_id' => $user->id, 'requester_id' => $request->user()->id, 'requester_name' => $request->user()->name, 'created_at' => now()->getTimestamp(), 'updated_at' => now()->getTimestamp()]);
            AdminMutation::audit($request, 'coin.request', $id, $data);

            return $this->profile($request, DB::table('coin_adjustments')->find($id));
        });

        return response()->json(['data' => $result], 201);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->role_key === 'admin', 403, '仅超级管理员拥有独立金币审核权限');
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:500'], 'version' => ['required', 'integer', 'min:1', 'max:9007199254740991']]);
        $result = AdminMutation::run($request, 'coin.review:'.$id, function () use ($request, $id, $data): array {
            $row = DB::table('coin_adjustments')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404, '申请不存在');
            abort_if((int) $row->requester_id === $request->user()->id, 403, '禁止审核本人申请');
            abort_unless($row->status === 'pending' && (int) $row->version === $data['version'], 409, '申请已审核或版本已过期');
            if ($data['decision'] === 'approve') {
                $user = User::whereKey($row->user_id)->lockForUpdate()->firstOrFail();
                abort_unless($user->status === 'active', 422, '用户状态不允许调整');
                $balance = (int) $user->{$row->currency} + (int) $row->amount;
                abort_unless(abs((int) $row->amount) <= config('wallet.adjustment_limit') && $balance >= 0 && $balance <= config('wallet.maximum_balance'), 422, '余额不足或超出额度');
                $user->{$row->currency} = $balance;
                $user->version++;
                $user->save();
                DB::table('wallet_entries')->insert(['user_id' => $user->id, 'adjustment_id' => $id, 'currency' => $row->currency, 'amount' => $row->amount, 'balance_after' => $balance, 'created_at' => now()->getTimestamp(), 'expires_at' => $row->currency === 'bonus' && $row->amount > 0 ? now()->addMonths(config('wallet.bonus_months'))->getTimestamp() : null]);
            }
            DB::table('coin_adjustments')->where('id', $id)->update(['status' => $data['decision'] === 'approve' ? 'approved' : 'rejected', 'reviewer_id' => $request->user()->id, 'reviewer_name' => $request->user()->name, 'review_reason' => $data['reason'] ?? null, 'version' => $row->version + 1, 'updated_at' => now()->getTimestamp()]);
            AdminMutation::audit($request, 'coin.review', $id, $data);

            return $this->profile($request, DB::table('coin_adjustments')->find($id));
        });

        return response()->json(['data' => $result]);
    }

    private function profile(Request $request, object $row): array
    {
        return ['id' => $row->id, 'uid' => $row->uid, 'currency' => $row->currency, 'amount' => (int) $row->amount, 'reason' => $row->reason, 'status' => $row->status, 'requester_id' => (int) $row->requester_id, 'requester_name' => $row->requester_name, 'reviewer_name' => $row->reviewer_name, 'review_reason' => $row->review_reason, 'created_at' => (int) $row->created_at, 'version' => (int) $row->version, 'can_review' => $row->status === 'pending' && $request->user()->role_key === 'admin' && (int) $row->requester_id !== $request->user()->id];
    }
}
