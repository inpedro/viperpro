<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Order;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $totalEarnings = Order::where('user_id', auth('api')->id())->where('type', 'win')->sum('amount');
        $totalBets = Order::where('user_id', auth('api')->id())->where('type', 'bet')->count();
        $sumBets = Order::where('user_id', auth('api')->id())->where('type', 'bet')->sum('amount');

        return response()->json([
            'status' => true,
            'user' => auth('api')->user(),
            'totalEarnings' => \Helper::amountFormatDecimal($totalEarnings),
            'totalBets' => \Helper::amountFormatDecimal($totalBets),
            'sumBets' => $sumBets,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function updateName(Request $request)
    {
        $rules = [
            'name' => 'required',
        ];

        $validator = \Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        if(auth('api')->user()->update(['name' => $request->name])) {
            return response()->json(['status' => true, 'message' => trans('Name was updated successfully')]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function uploadAvatar(Request $request)
    {
        $rules = [
            'avatar' => ['required','image','mimes:jpg,png,jpeg'],
        ];

        $validator = \Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $avatar = \Helper::upload($request->avatar)['path'];
        if(auth('api')->user()->update(['avatar' => $avatar])) {
            return response()->json(['status' => true, 'message' => trans('Avatar has been updated successfully')]);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateLanguage(Request $request)
    {
        $language = (string) $request->input("language");

        // Only locales this casino offers; previously any string was stored and applied.
        if (! \App\Core\Localization\Locales::isEnabled($language)) {
            return response()->json(["error" => trans("Language not available")], 422);
        }

        if (auth("api")->check()) {
            $user = auth("api")->user();
            $user->language = $language;
            $user->save();
        }

        return response()->json(["message" => trans("Language updated"), "language" => $language]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLanguage(Request $request)
    {
        // Already resolved by SetDefaultLanguage (user preference, Accept-Language, tenant default).
        return response()->json(["language" => app()->getLocale()]);
    }
}
