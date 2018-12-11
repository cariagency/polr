<?php

namespace App\Helpers;

use App\Models\Tag;
use App\Models\Link;
use App\Helpers\UserHelper;
use DB;

class TagHelper {

    static public function tagExists($tag) {
        return (Tag::where('tag', $tag)->count() > 0);
    }

    static public function userHasTag($user, $tag) {
        if (UserHelper::userIsAdmin($user->username)) {
            return true;
        }

        return (Link::where('creator', $user->username)->whereHas('tags', function ($query) use($tag) {
                    $query->where('tag', $tag);
                })->count() > 0);
    }

    static public function getTagLinks($tag) {
        // Get tag's links ids.
        $ids = Tag::where('tag', $tag)->get()->pluck('link_id');
        if (empty($ids)) {
            return [];
        }

        // Get tag's links and clicks.
        $links = DB::table('links')
                ->join('clicks', 'clicks.link_id', '=', 'links.id')
                ->select('links.id', 'links.short_url', 'links.long_url', DB::raw('COUNT(clicks.id) as clicks'))
                ->whereIn('links.id', $ids)
                ->groupBy('links.short_url', 'links.long_url')
                ->get();
        if (empty($links)) {
            return [];
        }

        // Get tag's links other tags.
        $tags = DB::table('tags')
                ->select('link_id', 'tag')
                ->whereIn('link_id', $ids)
                ->where('tag', '!=', $tag)
                ->get();

        // Add other tags to links list.
        $tmp = [];
        foreach ($tags as $t) {
            $tmp[$t->link_id][] = $t->tag;
        }
        foreach ($links as &$l) {
            $l->tags = isset($tmp[$l->id]) ? $tmp[$l->id] : [];
        }

        // Sort by clicks desc.
        usort($links, function($a, $b) {
            if ($a->clicks === $b->clicks) {
                return 0;
            }

            return ($a->clicks > $b->clicks) ? -1 : 1;
        });

        // Return.
        return $links;
    }

}
