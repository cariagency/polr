<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Helpers\LinkHelper;
use App\Helpers\TagHelper;
use App\Helpers\UserHelper;
use App\Helpers\StatsHelper;
use App\Exceptions\Api\ApiException;

class ApiAnalyticsController extends ApiController {

    public function lookupLinkStats(Request $request, $stats_type = false) {
        $user = $request->user;
        $response_type = $request->input('response_type') ?: 'json';

        if ($user->anonymous) {
            throw new ApiException('AUTH_ERROR', 'Anonymous access of this API is not permitted.', 401, $response_type);
        }

        if ($response_type != 'json') {
            throw new ApiException('JSON_ONLY', 'Only JSON-encoded data is available for this endpoint.', 401, $response_type);
        }

        $validator = \Validator::make($request->all(), [
                    'url_ending' => 'required|alpha_dash',
                    'stats_type' => 'alpha_num',
                    'left_bound' => 'date',
                    'right_bound' => 'date'
        ]);

        if ($validator->fails()) {
            throw new ApiException('MISSING_PARAMETERS', 'Invalid or missing parameters.', 400, $response_type);
        }

        $url_ending = $request->input('url_ending');
        $stats_type = $request->input('stats_type');
        $left_bound = $request->input('left_bound');
        $right_bound = $request->input('right_bound');

        // ensure user can only read own analytics or user is admin
        $link = LinkHelper::linkExists($url_ending);

        if ($link === false) {
            throw new ApiException('NOT_FOUND', 'Link not found.', 404, $response_type);
        }

        if (($link->creator != $user->username) &&
                !(UserHelper::userIsAdmin($user->username))) {
            // If user does not own link and is not an admin
            throw new ApiException('ACCESS_DENIED', 'Unauthorized.', 401, $response_type);
        }

        try {
            $stats = new StatsHelper($link->id, $left_bound, $right_bound);
        } catch (\Exception $e) {
            throw new ApiException('ANALYTICS_ERROR', $e->getMessage(), 400, $response_type);
        }

        if ($stats_type == 'day') {
            $fetched_stats = $stats->getDayStats();
        } else if ($stats_type == 'country') {
            $fetched_stats = $stats->getCountryStats();
        } else if ($stats_type == 'referer') {
            $fetched_stats = $stats->getRefererStats();
        } else {
            throw new ApiException('INVALID_ANALYTICS_TYPE', 'Invalid analytics type requested.', 400, $response_type);
        }

        return self::encodeResponse([
                    'url_ending' => $link->short_url,
                    'data' => $fetched_stats,
                        ], 'data_link_' . $stats_type, $response_type, false);
    }

    public function lookupTagStats(Request $request) {
        $user = $request->user;
        $response_type = $request->input('response_type') ?: 'json';

        if ($user->anonymous) {
            throw new ApiException('AUTH_ERROR', 'Anonymous access of this API is not permitted.', 401, $response_type);
        }

        if ($response_type != 'json') {
            throw new ApiException('JSON_ONLY', 'Only JSON-encoded data is available for this endpoint.', 401, $response_type);
        }

        $validator = \Validator::make($request->all(), [
                    'tag' => 'required',
                    'left_bound' => 'date',
                    'right_bound' => 'date'
        ]);

        if ($validator->fails()) {
            throw new ApiException('MISSING_PARAMETERS', 'Invalid or missing parameters.', 400, $response_type);
        }

        $tag = $request->input('tag');

        if (!TagHelper::tagExists($tag)) {
            throw new ApiException('NOT_FOUND', 'Tag not found.', 404, $response_type);
        }

        if (!TagHelper::userHasTag($user, $tag)) {
            // If user does not own tag and is not an admin
            throw new ApiException('ACCESS_DENIED', 'Unauthorized.', 401, $response_type);
        }

        try {
            $left_bound = $request->input('left_bound');
            $right_bound = $request->input('right_bound');
            $stats = new StatsHelper(null, $left_bound, $right_bound);
            $data = $stats->getTagStats($tag);
        } catch (\Exception $e) {
            throw new ApiException('ANALYTICS_ERROR', $e->getMessage(), 400, $response_type);
        }

        return self::encodeResponse(['tag' => $tag, 'data' => $data], "data_tag_{$tag}_stats", $response_type, false);
    }

    public function lookupLinksStats(Request $request, $stats_type = false) {
        $user = $request->user;
        $response_type = $request->input('response_type') ?: 'json';

        if ($user->anonymous) {
            throw new ApiException('AUTH_ERROR', 'Anonymous access of this API is not permitted.', 401, $response_type);
        }

        if ($response_type != 'json') {
            throw new ApiException('JSON_ONLY', 'Only JSON-encoded data is available for this endpoint.', 401, $response_type);
        }

        $validator = \Validator::make($request->all(), [
                    'search' => 'required_without:tags',
                    'stats_type' => 'alpha_num',
                    'left_bound' => 'date',
                    'right_bound' => 'date',
                    'tags' => 'required_without:search'
        ]);

        if ($validator->fails()) {
            throw new ApiException('MISSING_PARAMETERS', 'Invalid or missing parameters.', 400, $response_type);
        }

        $keywords = explode(',', $request->input('search'));
        $tags = explode(',', $request->input('tags'));
        $stats_type = $request->input('stats_type');
        $left_bound = $request->input('left_bound');
        $right_bound = $request->input('right_bound');

        if ($stats_type == 'day') {
            $stats_function = 'getDayStats';
        } else if ($stats_type == 'country') {
            $stats_function = 'getCountryStats';
        } else if ($stats_type == 'referer') {
            $stats_function = 'getRefererStats';
        } else {
            throw new ApiException('INVALID_ANALYTICS_TYPE', 'Invalid analytics type requested.', 400, $response_type);
        }

        if ($keywords && !$tags) {
            $links = LinkHelper::searchLinksByLongUrl($keywords);
        } else {
            $links = LinkHelper::getLinksByTags($tags);
        }
        if ($links === false) {
            throw new ApiException('NOT_FOUND', 'Nothing found.', 404, $response_type);
        }
        $is_admin = UserHelper::userIsAdmin($user->username);
        $data = [];
        foreach ($links as $link) {
            if (($link->creator == $user->username) ||
                    $is_admin) {
                // If user owns link or is an admin
                try {
                    $stats = new StatsHelper($link->id, $left_bound, $right_bound);
                } catch (\Exception $e) {
                    throw new ApiException('ANALYTICS_ERROR', $e->getMessage(), 400, $response_type);
                }
                if ($fetched_stats = $stats->$stats_function()) {
                    $data[] = [
                        'url_ending' => $link->short_url,
                        'long_url' => $link->long_url,
                        'data' => $fetched_stats,
                    ];
                }
            }
        }

        return self::encodeResponse($data, 'data_links_' . $stats_type, $response_type, false);
    }

    public function lookupTagLinksStats(Request $request) {
        $user = $request->user;
        $response_type = $request->input('response_type') ?: 'json';

        if ($user->anonymous) {
            throw new ApiException('AUTH_ERROR', 'Anonymous access of this API is not permitted.', 401, $response_type);
        }

        if ($response_type != 'json') {
            throw new ApiException('JSON_ONLY', 'Only JSON-encoded data is available for this endpoint.', 401, $response_type);
        }

        $validator = \Validator::make($request->all(), ['tag' => 'required']);
        if ($validator->fails()) {
            throw new ApiException('MISSING_PARAMETERS', 'Invalid or missing parameters.', 400, $response_type);
        }

        $tag = $request->input('tag');
        if (!TagHelper::tagExists($tag)) {
            throw new ApiException('NOT_FOUND', 'Tag not found.', 404, $response_type);
        }

        if (!TagHelper::userHasTag($user, $tag)) {
            // If user does not own tag and is not an admin
            throw new ApiException('ACCESS_DENIED', 'Unauthorized.', 401, $response_type);
        }

        try {
            $data = TagHelper::getTagLinks($tag);
        } catch (\Exception $e) {
            throw new ApiException('ANALYTICS_ERROR', $e->getMessage(), 400, $response_type);
        }

        return self::encodeResponse(['tag' => $tag, 'data' => $data], "data_tag_{$tag}_links_stats", $response_type, false);
    }

}
