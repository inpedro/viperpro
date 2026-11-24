<?php

namespace App\Http\Controllers\Api\ResponsibleGaming;

use App\Core\ResponsibleGaming\ResponsibleGaming;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ResponsibleGamingController extends Controller
{
    public function show()
    {
        return response()->json(ResponsibleGaming::present(ResponsibleGaming::for(auth('api')->user())));
    }

    public function limits(Request $request)
    {
        $rules = [];
        foreach (array_keys(ResponsibleGaming::PERIODS) as $period) {
            $rules[$period] = ['nullable', 'numeric', 'min:1', 'max:10000000'];
        }
        $data = $request->validate($rules);

        return response()->json(ResponsibleGaming::present(ResponsibleGaming::setDepositLimits(auth('api')->user(), $data)));
    }

    public function exclude(Request $request)
    {
        $data = $request->validate([
            'duration' => ['required', 'in:'.implode(',', array_keys(ResponsibleGaming::EXCLUSIONS))],
            'confirm'  => ['accepted'],
        ]);

        return response()->json(ResponsibleGaming::present(ResponsibleGaming::exclude(auth('api')->user(), $data['duration'])));
    }

    public function realityCheck(Request $request)
    {
        $data = $request->validate(['minutes' => ['nullable', 'integer', 'min:15', 'max:240']]);

        return response()->json(ResponsibleGaming::present(ResponsibleGaming::setRealityCheck(auth('api')->user(), $data['minutes'] ?? null)));
    }
}
