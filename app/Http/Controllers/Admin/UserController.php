<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminMutation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'banned'])], 'membership' => ['nullable', Rule::in(['none', 'weekly', 'monthly', 'yearly'])], 'page' => ['integer', 'min:1'], 'per_page' => ['integer', 'min:1', 'max:100']]);
        $query = User::query();
        if (! empty($data['q'])) {
            $query->where('uid', $data['q']);
        }
        foreach (['status', 'membership'] as $key) {
            if (! empty($data[$key])) {
                $query->where($key, $data[$key]);
            }
        }
        $result = $query->orderBy('id')->paginate($data['per_page'] ?? 10)->through(fn (User $user): array => self::profile($user));

        return response()->json(['data' => $result]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => self::profile(User::findOrFail($id))]);
    }

    public function status(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'banned'])], 'reason' => ['required', 'string', 'min:1', 'max:500'], 'version' => ['required', 'integer', 'min:1', 'max:9007199254740991']]);
        $result = DB::transaction(function () use ($request, $id, $data): array {
            $user = User::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($user->version === $data['version'], 409, '用户状态已变更，请刷新');
            $before = self::profile($user);
            $user->status = $data['status'];
            $user->ban_reason = $data['status'] === 'banned' ? $data['reason'] : null;
            $user->version++;
            $user->save();
            if ($user->status === 'banned') {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            AdminMutation::audit($request, 'user.status', $id, ['before' => $before, 'after' => self::profile($user), 'reason' => $data['reason']]);

            return self::profile($user);
        });

        return response()->json(['data' => $result]);
    }

    public static function profile(User $user): array
    {
        return ['id' => (string) $user->id, 'uid' => $user->uid ?? (string) $user->id, 'login_provider' => $user->login_provider, 'member_coins' => (int) $user->member_coins, 'bonus' => (int) $user->bonus, 'membership' => $user->membership, 'unlocked_episodes' => (int) $user->unlocked_episodes, 'status' => $user->status, 'registered_at' => $user->created_at?->getTimestamp(), 'ban_reason' => $user->ban_reason, 'version' => $user->version];
    }
}
