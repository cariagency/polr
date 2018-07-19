<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Helpers\CryptoHelper;
use App\Models\Tag;
use Carbon\Carbon;
use Cache;

class IndexController extends Controller {
    /**
     * Show the index page.
     *
     * @return Response
     */
    public function showIndexPage(Request $request) {
        if (env('POLR_SETUP_RAN') != true) {
            return redirect(route('setup'));
        }

        if (!env('SETTING_PUBLIC_INTERFACE') && !self::isLoggedIn()) {
            if (env('SETTING_INDEX_REDIRECT')) {
                return redirect()->to(env('SETTING_INDEX_REDIRECT'));
            }
            else {
                return redirect()->to(route('login'));
            }
        }

        $tags = Cache::remember('tags', 5, function () {
            return Tag::groupBy('tag')->where('created_at', '>', Carbon::now()->subDays(30))->orderBy('tag')->get()->pluck('tag')->toArray();
        });

        return view('index', ['tags' => $tags, 'large' => true]);
    }
}
