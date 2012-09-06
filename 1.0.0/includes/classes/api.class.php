<?php

class API extends Base {
    public $version = "1.0.0";
    
    /*!
     * ===
     * Public functions
     * ===
     */
    
    public function fetch_profile($gamertag) {
        $gamertag = trim($gamertag);
        $url = "http://live.xbox.com/en-US/Profile?gamertag=" . urlencode($gamertag);
        $key = $this->version . ":profile." . $gamertag;
        
        $data = $this->__cache->fetch($key);
        if(!$data) {
            $data = $this->fetch_url($url);
            $freshness = "new";
            $this->__cache->store($key, $data, 180);
        } else {
            $freshness = "from cache";
        }
        
        if(stripos($result, "<section class=\"contextRail custom\">")) {
            $user = array();
            
            $user['gamertag'] = trim($gamertag);
            $user['motto'] = $this->clean(trim(strip_tags($this->find($result, "<div class=\"motto\">", "</div>"))));
            $user['avatar']['full'] = "http://avatar.xboxlive.com/avatar/" . $gamertag . "/avatar-body.png";
            $user['avatar']['small'] = "http://avatar.xboxlive.com/avatar/" . $gamertag . "/avatarpic-s.png";
            $user['avatar']['large'] = "http://avatar.xboxlive.com/avatar/" . $gamertag . "/avatarpic-l.png";
            $user['gamerscore'] = trim($this->find($result, "<div class=\"gamerscore\">", "</div>"));
            $user['presence'] = trim(str_replace("\r\n", " - ", trim($this->find($result, "<div class=\"presence\">", "</div>"))));
            
            $user['gamertag'] = str_replace(array("&#39;s Profile", "'s Profile"), "", trim($this->find($result, "<h1 class=\"pageTitle\">", "</h1>")));
            $user['location'] = trim(strip_tags(str_replace("<label>Location:</label>", "", trim($this->find($result, "<div class=\"location\">", "</div>")))));
            $user['biography'] = trim(strip_tags(str_replace("<label>Bio:</label>", "", trim($this->find($result, "<div class=\"bio\">", "</div>")))));
            
            $recent_games = $this->fetch_games($gamertag);
            $user['recentactivity'] = array($recent_games['games'][0], $recent_games['games'][1], $recent_games['games'][2], $recent_games['games'][3], $recent_games['games'][4]);
            
            $user['freshness'] = $freshness;
            $user['runtime'] = round(microtime(true) - $this->runtime, 3);
            
            return $this->array_filter_recursive($user);
        } else if(stripos($result, "ERROR_404")) {
            $this->error = 501;
            return false;
        } else {
            $this->error = 500;
            if($this->cache_files) $this->remove_from_cache($cache_file);
            $this->force_new_login();
            return false;
        }
    }
    
    public function fetch_achievements($gamertag, $gameid) {
        $gamertag = trim($gamertag);
        $url = "http://live.xbox.com/en-US/Activity/Details?titleId=" . urlencode($gameid) . "&compareTo=" . urlencode($gamertag);
        
        if($this->cache_files) {
            $cache_file = $this->root . "cache/achievements/" . preg_replace("#\W#", "", $gamertag) . "." . $gameid . ".cache";
            $cache = $this->launch_from_cache($url, $cache_file, 600);
            $result = $cache['content'];
            $freshness = $cache['freshness'];
        } else {
            $result = $this->launch_page($url);
            $freshness = "new";
        }
        
        $json = $this->find($result, "broker.publish(routes.activity.details.load, ", ");");
        $json = json_decode($json, true);
        
        if(!empty($json)) {
            $achievements = array();
            
            $achievements['gamertag'] = $g = $json['Players'][0]['Gamertag'];
            $achievements['game'] = $this->clean($json['Game']['Name']);
            $achievements['gamerscore']['current'] = $json['Game']['Progress'][$g]['Score'];
            $achievements['gamerscore']['outof'] = $json['Game']['PossibleScore'];
            //$achievements['achievements']['current'] = $json['Game']['Progress'][$g]['Achievements'];
            //$achievements['achievements']['outof'] = $json['Game']['PossibleAchievements'];
            $achievements['progress'] = $json['Players'][0]['PercentComplete'] . "%";
            $achievements['lastplayed'] = substr(str_replace(array("/Date(", ")/"), "", $json['Game']['Progress'][$g]['LastPlayed']), 0, 10);
            
            $query = $this->mysql->query("SELECT * FROM xbox_games WHERE id = %d LIMIT 1", $gameid);
            if($this->mysql->num_rows($query) > 0) {
                $game = $this->mysql->fetch_assoc($query);
                if((time() - $game['cached']) > (60 * 60 * 24 * 7)) {
                    $this->refresh_achievements($gameid, $achievements['game']);
                    $this->mysql->query("UPDATE xbox_games SET cached = %d WHERE id = %d LIMIT 1", time(), $gameid);
                }
            } else {
                $this->refresh_achievements($gameid, $achievements['game']);
                $this->mysql->query("INSERT INTO xbox_games (id, cached) VALUES (%d, %d)", $gameid, time());
            }
            
            $query = $this->mysql->query("SELECT * FROM xbox_achievements WHERE gameid = %d", $gameid);
            $game['achievements'] = array();
            while($row = $this->mysql->fetch_assoc($query)) {
                $game['achievements'][strtolower($row['title'])] = array(
                    "id" => $row['id'],
                    "artwork" => $row['image'],
                    "description" => $row['description'],
                    "gamerscore" => $row['gamerscore'],
                    "secret" => ($row['secret'] == 1) ? true : false
                );
            }
            
            $i = 0;
            foreach($json['Achievements'] as $achievement) {
                if(!empty($achievement['Name'])) {
                    $achievements['achievements'][$i]['id'] = $achievement['Id'];
                    $achievements['achievements'][$i]['title'] = $this->clean($achievement['Name']);
                    
                    if(!empty($achievement['Description'])) {
                        $achievements['achievements'][$i]['description'] = $this->clean($achievement['Description']);
                    } else if(!empty($game['achievements'][strtolower($achievements['achievements'][$i]['title'])]['description'])) {
                        $achievements['achievements'][$i]['description'] = $this->clean($game['achievements'][strtolower($achievements['achievements'][$i]['title'])]['description']);
                    }
                    
                    $achievements['achievements'][$i]['artwork']['locked'] = $achievement['TileUrl'];
                    if(!empty($game['achievements'][strtolower($achievements['achievements'][$i]['title'])]['artwork'])) {
                        $achievements['achievements'][$i]['artwork']['unlocked'] = $game['achievements'][strtolower($achievements['achievements'][$i]['title'])]['artwork'];
                    }
                    
                    if(!empty($achievement['Score'])) {
                        $achievements['achievements'][$i]['gamerscore'] = $achievement['Score'];
                    } else if(!empty($game['achievements'][strtolower($achievements['achievements'][$i]['title'])]['gamerscore'])) {
                        $achievements['achievements'][$i]['gamerscore'] = $game['achievements'][strtolower($achievements['achievements'][$i]['title'])]['gamerscore'];
                    }
                    
                    if(!empty($achievement['IsHidden'])) {
                        $achievements['achievements'][$i]['secret'] = ($achievement['IsHidden']) ? "true" : "false";
                    } else if(isset($game['achievements'][strtolower($achievements['achievements'][$i]['title'])]['secret'])) {
                        $achievements['achievements'][$i]['secret'] = ($game['achievements'][strtolower($achievements['achievements'][$i]['title'])]['secret']) ? "true" : "false";
                    }
                    
                    if(!empty($achievement['EarnDates'][$g]['EarnedOn'])) {
                        $achievements['achievements'][$i]['unlocked'] = "true";
                        $achievements['achievements'][$i]['unlockdate'] = substr(str_replace(array("/Date(", ")/"), "", $achievement['EarnDates'][$g]['EarnedOn']), 0, 10);
                    } else {
                        $achievements['achievements'][$i]['unlocked'] = "false";
                    }
                    $i++;
                }
            }
            
            $achievements['freshness'] = $freshness;
            $achievements['runtime'] = round(microtime(true) - $this->runtime, 3);
            
            return $this->array_filter_recursive($achievements);
        } else if(stripos($result, "ERROR_404")) {
            $this->error = 502;
            return false;
        } else {
            $this->error = 500;
            if($this->cache_files) $this->remove_from_cache($cache_file);
            $this->force_new_login();
            return false;
        }
    }
    
    public function fetch_games($gamertag) {
        $gamertag = trim($gamertag);
        $url = "http://live.xbox.com/en-US/Activity?compareTo=" . urlencode($gamertag);
        
        if($this->cache_files) {
            $cache_file = $this->root . "cache/games/" . preg_replace('#\W#', '', $gamertag) . ".cache";
            $cache = $this->launch_from_cache($url, $cache_file, 600);
            $result = $cache['content'];
            $freshness = $cache['freshness'];

            if($freshness == "new") {
                $json = "http://live.xbox.com/en-US/Activity/Summary?compareTo=" . urlencode($gamertag) . "&lc=1033";
                $post_data = "__RequestVerificationToken=" . urlencode(trim($this->find($result, "<input name=\"__RequestVerificationToken\" type=\"hidden\" value=\"", "\" />")));
                $headers = array("X-Requested-With: XMLHttpRequest", "Content-Type: application/x-www-form-urlencoded; charset=UTF-8");
                
                $json = $this->launch_page($json, $url, 10, $post_data, $headers);
                $freshness = "new";
                $this->add_to_cache($json, $cache_file);
            } else {
                $json = $result;
            }
        } else {
            $result = $this->launch_page($url);
            
            $json = "http://live.xbox.com/en-US/Activity/Summary?compareTo=" . urlencode($gamertag) . "&lc=1033";
            $post_data = "__RequestVerificationToken=" . urlencode(trim($this->find($result, "<input name=\"__RequestVerificationToken\" type=\"hidden\" value=\"", "\" />")));
            $headers = array("X-Requested-With: XMLHttpRequest", "Content-Type: application/x-www-form-urlencoded; charset=UTF-8");
            
            $json = $this->launch_page($json, $url, 10, $post_data, $headers);
            $freshness = "new";
        }
        
        $json = json_decode($json, true);
        
        if($json['Success'] == "true" && $json['Data']['Players'][0]['Gamertag'] != "Xbox API") {
            $games = array();
            $json = $json['Data'];
            
            $games['gamertag'] = $g = $json['Players'][0]['Gamertag'];
            $games['gamerscore'] = $json['Players'][0]['Gamerscore'];
            $games['totalgames'] = $json['Players'][0]['GameCount'];
            $games['progress'] = $json['Players'][0]['PercentComplete'] . "%";
            
            $i = 0;
            foreach($json['Games'] as $game) {
                if($game['Progress'][$g]['LastPlayed'] !== "null") {
                    $games['games'][$i]['id'] = $game['Id'];
                    $games['games'][$i]['title'] = $this->clean($game['Name']);
                    $games['games'][$i]['artwork'] = $game['LargeBoxArt'];
                    $games['games'][$i]['gamerscore']['current'] = $game['Progress'][$g]['Score'];
                    $games['games'][$i]['gamerscore']['outof'] = $game['PossibleScore'];
                    $games['games'][$i]['achievements']['current'] = $game['Progress'][$g]['Achievements'];
                    $games['games'][$i]['achievements']['outof'] = $game['PossibleAchievements'];
                    $games['games'][$i]['lastplayed'] = substr(str_replace(array("/Date(", ")/"), "", $game['Progress'][$g]['LastPlayed']), 0, 10);
                    $i++;
                }
            }
            
            $games['freshness'] = $freshness;
            $games['runtime'] = round(microtime(true) - $this->runtime, 3);
            
            return $this->array_filter_recursive($games);
        } else if($json['Data']['Players'][0]['Gamertag'] == "Xbox API") {
            $this->error = 501;
            return false;
        } else {
            $this->error = 500;
            if($this->cache_files) $this->remove_from_cache($cache_file);
            $this->force_new_login();
            return false;
        }
    }
}

?>