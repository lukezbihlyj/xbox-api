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
        
        if(stripos($data, "<section class=\"contextRail custom\">")) {
            $user = array();
            
            $user['gamertag'] = trim($gamertag);
            $user['motto'] = $this->clean(trim(strip_tags($this->find($data, "<div class=\"motto\">", "</div>"))));
            $user['avatar']['full'] = "http://avatar.xboxlive.com/avatar/" . $gamertag . "/avatar-body.png";
            $user['avatar']['small'] = "http://avatar.xboxlive.com/avatar/" . $gamertag . "/avatarpic-s.png";
            $user['avatar']['large'] = "http://avatar.xboxlive.com/avatar/" . $gamertag . "/avatarpic-l.png";
            $user['gamerscore'] = trim($this->find($result, "<div class=\"gamerscore\">", "</div>"));
            $user['presence'] = trim(str_replace("\r\n", " - ", trim($this->find($data, "<div class=\"presence\">", "</div>"))));
            
            $user['gamertag'] = str_replace(array("&#39;s Profile", "'s Profile"), "", trim($this->find($data, "<h1 class=\"pageTitle\">", "</h1>")));
            $user['location'] = trim(strip_tags(str_replace("<label>Location:</label>", "", trim($this->find($data, "<div class=\"location\">", "</div>")))));
            $user['biography'] = trim(strip_tags(str_replace("<label>Bio:</label>", "", trim($this->find($data, "<div class=\"bio\">", "</div>")))));
            
            $recent_games = $this->fetch_games($gamertag);
            $user['recentactivity'] = array($recent_games['games'][0], $recent_games['games'][1], $recent_games['games'][2], $recent_games['games'][3], $recent_games['games'][4]);
            
            $user['freshness'] = $freshness;
            
            return array_filter_recursive($user);
        } else if(stripos($data, "ERROR_404")) {
            $this->error = 501;
            return false;
        } else {
            $this->error = 500;
            $this->__cache->remove($key);
            $this->force_new_login();
            
            return false;
        }
    }
    
    public function fetch_achievements($gamertag, $gameid) {
        $gamertag = trim($gamertag);
        $url = "http://live.xbox.com/en-US/Activity/Details?titleId=" . urlencode($gameid) . "&compareTo=" . urlencode($gamertag);
        $key = $this->version . ":achievements." . $gamertag . "." . $gameid;
        
        $data = $this->__cache->fetch($key);
        if(!$data) {
            $data = $this->fetch_url($url);
            $freshness = "new";
            $this->__cache->store($key, $data, 600);
        } else {
            $freshness = "from cache";
        }
        
        $json = $this->find($data, "broker.publish(routes.activity.details.load, ", ");");
        $json = json_decode($json, true);
        
        if(!empty($json)) {
            $achievements = array();
            
            $achievements['gamertag'] = $g = $json['Players'][0]['Gamertag'];
            $achievements['game'] = $this->clean($json['Game']['Name']);
            $achievements['gamerscore']['current'] = $json['Game']['Progress'][$g]['Score'];
            $achievements['gamerscore']['outof'] = $json['Game']['PossibleScore'];
            $achievements['progress'] = $json['Players'][0]['PercentComplete'] . "%";
            $achievements['lastplayed'] = substr(str_replace(array("/Date(", ")/"), "", $json['Game']['Progress'][$g]['LastPlayed']), 0, 10);
            
            $i = 0;
            foreach($json['Achievements'] as $achievement) {
                if(!empty($achievement['Name'])) {
                    $achievements['achievements'][$i]['id'] = $achievement['Id'];
                    $achievements['achievements'][$i]['title'] = $this->clean($achievement['Name']);
                    $achievements['achievements'][$i]['artwork']['locked'] = $achievement['TileUrl'];
                    
                    if(!empty($achievement['Description'])) {
                        $achievements['achievements'][$i]['description'] = $this->clean($achievement['Description']);
                    }
                    
                    if(!empty($achievement['Score'])) {
                        $achievements['achievements'][$i]['gamerscore'] = $achievement['Score'];
                    }
                    
                    if(!empty($achievement['IsHidden'])) {
                        $achievements['achievements'][$i]['secret'] = ($achievement['IsHidden']) ? "true" : "false";
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
            
            return array_filter_recursive($achievements);
        } else if(stripos($data, "ERROR_404")) {
            $this->error = 502;
            return false;
        } else {
            $this->error = 500;
            $this->__cache->remove($key);
            $this->force_new_login();
            
            return false;
        }
    }
    
    public function fetch_games($gamertag) {
        $gamertag = trim($gamertag);
        $url = "http://live.xbox.com/en-US/Activity?compareTo=" . urlencode($gamertag);
        $key = $this->version . ":games." . $gamertag;
        
        $data = $this->__cache->fetch($key);
        if(!$data) {
            $data = $this->fetch_url($url);
            $post_data = "__RequestVerificationToken=" . urlencode(trim($this->find($data, "<input name=\"__RequestVerificationToken\" type=\"hidden\" value=\"", "\" />")));
            $headers = array("X-Requested-With: XMLHttpRequest", "Content-Type: application/x-www-form-urlencoded; charset=UTF-8");
            
            $data = $this->fetch_url("http://live.xbox.com/en-US/Activity/Summary?compareTo=" . urlencode($gamertag) . "&lc=1033", $url, 10, $post_data, $headers);
            $freshness = "new";
            $this->__cache->store($key, $data, 600);
        } else {
            $freshness = "from cache";
        }
        
        $json = json_decode($data, true);
        
        if($json['Success'] == "true" && $json['Data']['Players'][0]['Gamertag'] != "Xbox API") {
            $json = $json['Data'];
            $games = array();
            
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
            
            return array_filter_recursive($games);
        } else if($json['Data']['Players'][0]['Gamertag'] == "Xbox API") {
            $this->error = 501;
            return false;
        } else {
            $this->error = 500;
            $this->__cache->remove($key);
            $this->force_new_login();
            
            return false;
        }
    }
}

?>