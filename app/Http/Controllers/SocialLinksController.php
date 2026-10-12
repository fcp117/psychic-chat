<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SocialLinksController extends Controller
{
    public function index() {
        return Inertia::render('Admin/SocialLinks', ['links'=>DB::table('social_links')->pluck('url','platform')]);
    }

    public function update(Request $request) {
        abort_unless($request->user()?->role === 'admin', 403);
        $rules = [];
        foreach (['facebook','instagram'] as $platform) {
            $rules[$platform] = ['present','nullable','string','max:2048','url:https', function ($attribute, $value, $fail) use ($platform) {
                $parts = parse_url($value);
                $host = strtolower($parts['host'] ?? '');
                if (!in_array($host, [$platform.'.com', 'www.'.$platform.'.com'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
                    $fail('Use an HTTPS link on '.$platform.'.com (without login details or a custom port).');
                }
            }];
        }
        $values = $request->validate($rules);
        DB::transaction(function () use ($request, $values) {
            $before = DB::table('social_links')->orderBy('platform')->lockForUpdate()->get();
            foreach ($values as $platform=>$url) DB::table('social_links')->where('platform',$platform)->update(['url'=>$url,'updated_at'=>now()]);
            DB::table('admin_audits')->insert(['actor_id'=>$request->user()->id,'action'=>'social_links.updated','target'=>'social_links','before'=>json_encode($before),'after'=>json_encode($values),'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success', 'Social links saved. Blank links are hidden on Home.');
    }
}
