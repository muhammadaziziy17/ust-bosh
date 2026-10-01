<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuditController extends Controller
{
    public function index(Request $request): View
    {
        $action = (string) $request->query('action', 'all');
        $shopId = (int) $request->query('shop', 0);

        $actions = AdminAuditLog::query()->distinct()->orderBy('action')->pluck('action');

        if (! in_array($action, $actions->all(), true)) {
            $action = 'all';
        }

        $logs = AdminAuditLog::query()
            ->with(['actor:id,name', 'shop:id,name'])
            ->when($shopId > 0, fn ($query) => $query->where('shop_id', $shopId))
            ->when($action !== 'all', fn ($query) => $query->where('action', $action))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit', [
            'logs' => $logs,
            'shops' => Shop::query()->orderBy('name')->get(['id', 'name']),
            'actions' => $actions,
            'action' => $action,
            'shopId' => $shopId,
        ]);
    }
}
