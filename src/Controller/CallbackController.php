<?php

namespace Iyzico\IyzipayLaravel\Controller;

use Illuminate\Routing\Controller; // ✅ Changed from App\Http\Controllers\Controller
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Iyzico\IyzipayLaravel\IyzipayLaravel;

class CallbackController extends Controller
{
    public function threedsCallback(Request $request): RedirectResponse
    {
        // Use 'input' method instead of magic property access for better safety
        if ($request->input('status') !== 'success') {

            // Resolve the service using the container
            app(IyzipayLaravel::class)->cancelThreedsPayment($request);

            return redirect()->route('iyzico.callback')
                ->with(['request' => $request->all()]);
        }

        $transaction = app(IyzipayLaravel::class)->threedsPayment($request);

        return redirect()->route('iyzico.callback')
            ->with([
                'request'     => $request->all(),
                'transaction' => $transaction
            ]);
    }

    public function bkmCallback(Request $request)
    {
        // ⚠️ Debug code detected. Ensure this is handled before production.
        dd($request->all());

        /* // Uncomment and update when BKM logic is ready:

        if ($request->input('status') !== 'success') {
           app(IyzipayLaravel::class)->cancelThreedsPayment($request);
           return redirect()->route('iyzico.callback')
                            ->with(['request' => $request->all()]);
        }

        $transaction = app(IyzipayLaravel::class)->threedsPayment($request);

        return redirect()->route('iyzico.callback')
                         ->with(['request' => $request->all(), 'transaction' => $transaction]);
        */
    }
}
