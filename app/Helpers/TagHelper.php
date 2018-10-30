<?php

namespace App\Helpers;

use App\Models\Tag;
use App\Models\Link;
use App\Helpers\UserHelper;

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

}
