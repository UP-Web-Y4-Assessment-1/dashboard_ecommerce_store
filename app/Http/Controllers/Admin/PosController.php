<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\PosCustomer;
use App\Models\Product;
use App\Models\Sell;
use App\Models\Sell_details;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Expr\Array_;
use PDF;

class PosController extends Controller
{
    public function posView()
    {

        $common_data = new Array_();
        $common_data->title = 'Sell Product';
        $productList = Product::where('status', 1)->where('deleted', 0)->paginate(12);
        $posCustomerList = PosCustomer::where('status', 1)->where('deleted', 0)->get();
        $bankList = BankAccount::where('status', 1)->where('deleted', 0)->get();

        return view('adminPanel.pos.pos')->with(compact('posCustomerList', 'bankList', 'common_data'));
    }

    public function posCustomerList()
    {
        $posCustomer = PosCustomer::where('deleted', 0)->get();
        return view('adminPanel.pos.pos_customer')->with(compact('posCustomer'));
    }

    public function posCustomerStore(Request $request)
    {
        $pusCustomer = new PosCustomer();
        $pusCustomer->name = $request->name;
        $pusCustomer->phone = $request->phone;
        $pusCustomer->email = $request->email;
        $pusCustomer->address = $request->address;
        $pusCustomer->save();
        return redirect()->back()->with('success', 'Customer Created Successfully');
    }

    public function posCustomerUpdate(Request $request)
    {
        $pusCustomer = PosCustomer::find($request->id);
        $pusCustomer->name = $request->name;
        $pusCustomer->phone = $request->phone;
        $pusCustomer->email = $request->email;
        $pusCustomer->address = $request->address;
        $pusCustomer->save();
        return redirect()->back()->with('success', 'Customer Info Updated Successfully');
    }

    public function getPostProductList()
    {
        $productList = Product::where('status', 1)->where('deleted', 0)->paginate(12);

        return view('adminPanel.pos._card_product_list')->with(compact('productList'))->render();
    }

    public function postProductSearch(Request $request)
    {
        $productList = Product::where('status', 1)->where('deleted', 0)->where('code', 'LIKE', '%' . $request->product_info . '%')->orWhere('name', 'LIKE', '%' . $request->product_info . '%')->get();

        $totalItem = $productList->count();
        $uniqProductId = 0;
        if ($totalItem == 1) {
            $uniqProductId = $productList[0]->id;
        }
        $list = view('adminPanel.pos._card_product_list')->with(compact('productList'))->render();
        $data = [$totalItem, $list, $uniqProductId];
        return $data;
    }

    public function sellItemGet(Request $request)
    {
        $productinfo = Product::find($request->product_id);
        $discount = 0;
        if ($productinfo->discount_type == 0) {
            $discount = $productinfo->discount;
        }
        if ($productinfo->discount_type == 1) {
            $discount = ($productinfo->discount * $productinfo->current_sale_price / 100);
        }
        return view('adminPanel.pos._pos_item_list')->with(compact('productinfo', 'discount'))->render();
    }

    public function posCustomerStoreInPos(Request $request)
    {
        $pusCustomer = new PosCustomer();
        $pusCustomer->name = $request->name;
        $pusCustomer->phone = $request->phone;
        $pusCustomer->email = $request->email;
        $pusCustomer->address = $request->address;
        $pusCustomer->save();
        $customer_id = $pusCustomer->id;
        $posCustomerList = PosCustomer::where('status', 1)->where('deleted', 0)->get();
        return view('adminPanel.pos._pos_customer_list')->with(compact('posCustomerList', 'customer_id'));
    }

    public function posPaymentStore(Request $request)
{
    DB::beginTransaction();
    try {
        $discountTotal = $request->filled('total_discount') ? $request->total_discount : 0;

        $sell = new Sell();
        $sell->total_payable_amount = $request->total_payable;
        $sell->total_discount = $discountTotal;
        $sell->total_paid = $request->total_paid;
        $sell->total_due = $request->total_payable - $request->total_paid;
        $sell->customer_id = $request->customer_id;
        $sell->bank_id = $request->bank_id;
        $sell->sell_type = 1;
        $sell->sell_by = 1;
        $sell->date = Carbon::now();
        $sell->created_at = Carbon::now();
        $sell->save();

        $sell->invoice_id = 1000 + $sell->id;
        $sell->save();

        foreach ($request->product_id as $key => $product_id) {
            $unitPayable = $request->product_unit_price[$key] - $request->product_discount[$key];

            $productSell = new Sell_details();
            $productSell->sell_id = $sell->id;
            $productSell->product_id = $product_id;
            // $productSell->total_discount = $request->product_discount[$key];
            $productSell->total_discount = $discountTotal;

            $productSell->sale_quantity = $request->sell_qty[$key];
            $productSell->unit_product_cost = $request->product_cost[$key];
            $productSell->unit_sell_price = $request->product_unit_price[$key];
            $productSell->total_payable_amount = $unitPayable * $request->sell_qty[$key];
            $productSell->save();

            $product = Product::find($product_id);
            $availableProduct = $product->available_quantity;
            $qty = $request->sell_qty[$key];
            $total_qty = $availableProduct - $qty;
            $product->available_quantity = $total_qty;
            $product->save();
        }

        DB::commit();
        return redirect()->back()->with('success', 'Successfully Payment Completed');
    } catch (\Throwable $e) {
        print("Exception: " . $e->getMessage());
        DB::rollBack();
        // return redirect()->back()->with('error', 'Failed to complete payment');
    }
}


    public function sellList()
    {
        $common_data = new Array_();
        $common_data->title = 'Sell List';
        $sellList = Sell::where('sell_type', 1)->where('deleted', 0)->orderBy('id', 'desc')->get();
        return view('adminPanel.pos.pos_sell')->with(compact('sellList', 'common_data'));

    }

    public function sellInvoice(Request $request)
    {

        $selldata = Sell::with('customer')->with('sellDetail')->find($request->id);

        $data = [
            'sell' => $selldata
        ];

        $pdf = PDF::loadView('adminPanel.pos.sell_invoice', $data);
//      return view('adminPanel.pos.sell_invoice');
//      return $pdf->download('buy_invoice.pdf');
        return $pdf->stream('buy_invoice.pdf');

    }
    // public function sellDelete(Request $request)
    // {

    
    //     $sell = Sell::find($request->id);
    //     $sellDetail = Sell_details::find($request->id);
    //     $sellDetail = Sell_details::join('sells', 'sell_details.sell_id', '=', 'sells.id')
    //             ->select('sell_details.*')
    //             ->where('sells.id', $request->id)
    //             ->get();

    //     $sell->deleted = 1;
    //     $sellDetail->deleted = 1;

    //     // Save the changes
    //     $sell->save();
    //     $sellDetail->save();

    //     // Redirect back with a success message
    //     return redirect()->back()->with('success', 'All Subcategories Successfully Deleted');
    // }
    public function sellDelete(Request $request)
{
    // Find the sell record
    $sell = Sell::find($request->id);

    // Find the sell details associated with the sell record
    $sellDetails = Sell_details::join('sells', 'sell_details.sell_id', '=', 'sells.id')
        ->select('sell_details.*')
        ->where('sells.id', $request->id)
        ->get();

    // Set the 'deleted' flag for the sell record
    $sell->deleted = 1;

    // Set the 'deleted' flag for each sell detail record
    foreach ($sellDetails as $sellDetail) {
        $sellDetail->deleted = 1;
        $sellDetail->save(); // Save each sell detail record
    }

    // Save the changes to the sell record
    $sell->save();

    // Redirect back with a success message
    return redirect()->back()->with('success', 'All Subcategories Successfully Deleted');
}



}
