<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Traits\Affiliates\AffiliateHistoryTrait;
use App\Traits\Gateways\BsPayTrait;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;

class BsPayController extends Controller
{
    use BsPayTrait, AffiliateHistoryTrait;


    /**
     * @dev victormsalatiel
     * @param Request $request
     * @return null
     */
    public function getQRCodePix(Request $request)
    {
        return self::requestQrcode($request);
    }

    /**
     * Store a newly created resource in storage.
     * @dev victormsalatiel
     */
    public function callbackMethod(Request $request)
    {
        // DISABLED: the previous implementation credited wallets on an unauthenticated callback
        // without verifying with BsPay. Its routes are not mounted (routes/web.php). Re-enable only
        // after a server-to-server verification adapter has been built against BsPay's official docs.
        \App\Core\Security\CallbackLog::record("payment", "bspay", $request, "rejected", "integration disabled");
        return response()->json(["status" => false, "message" => "integration disabled"], 410);
    }

    /**
     * Show the form for creating a new resource.
     * @dev victormsalatiel
     */
    public function consultStatusTransactionPix(Request $request)
    {
        return self::consultStatusTransaction($request);
    }

    /**
     * Display the specified resource.
     * @dev victormsalatiel
     */
    public function withdrawalFromModal($id)
    {
        abort(410, "BsPay integration disabled pending verification.");
    }
}
