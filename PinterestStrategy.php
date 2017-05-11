<?php
/**
 * Pinterest strategy for Opauth
 * based on https://developers.pinterest.com/docs/api/overview/?
 *
 * More information on Opauth: http://opauth.org
 *
 * @author       Adrian Martinez
 * @email        amartinez@adnbp.com
 * @link         http://opauth.org
 * @package      Opauth.PinterestStrategy
 * @license      MIT License
 */
class PinterestStrategy extends OpauthStrategy {
    /**
     * Compulsory config keys, listed as unassociative arrays
     * eg. array('app_id', 'app_secret');
     */
    public $expects = array('client_id', 'client_secret');

    /**
     * Optional config keys with respective default values, listed as associative arrays
     * eg. array('scope' => 'email');
     */
    public $defaults = array(
        'redirect_uri' => '{complete_url_to_strategy}int_callback',
        'scope' => 'read_public,write_public,read_relationships,write_relationships'
    );

    /**
     * Auth request
     */
    public function request(){
        $url = 'https://api.pinterest.com/oauth/';

        $params = array(
            'response_type' =>'code',
            'redirect_uri'=> $this->strategy['redirect_uri'],
            'client_id' => $this->strategy['client_id'],
            'state' => substr(md5(rand()), 0, 7)
        );

        if (!empty($this->strategy['scope'])) $params['scope'] = $this->strategy['scope'];

        // redirect to generated url
        $this->clientGet($url, $params);
    }

    /**
     * Internal callback, after Pinterest's OAuth
     * based on https://developers.pinterest.com/docs/api/users/ model
     */
    public function int_callback(){
        if (array_key_exists('code', $_GET) && !empty($_GET['code'])){
            $url = 'https://api.pinterest.com/v1/oauth/token';

            $params = array(
                'grant_type' => 'authorization_code',
                'client_id' =>$this->strategy['client_id'],
                'client_secret' => $this->strategy['client_secret'],
                'code' => trim($_GET['code'])
            );

            $response = $this->serverPost($url, $params, null, $headers);
            $results = json_decode($response,true);

            if (!empty($results) && !empty($results['access_token'])){
                $userinfo = $this->userinfo($results['access_token']);

                $this->auth = array(
                    'provider' => 'Pinterest',
                    'uid' => $userinfo['id'],
                    'info' => array(
                        'name' => $userinfo['first_name'].(($userinfo['last_name'])?' '.$userinfo['last_name']:''),
                        'nickname' => $userinfo['username'],
                        'image' => $userinfo['image']['60x60']['url'],
                        'bio' => $userinfo['bio'],
                        'url'=> $userinfo['url']
                    ),
                    'credentials' => array(
                        'token' => $results['access_token'],
                    ),
                    'raw' => $userinfo
                );

                if (!empty($userinfo->website)) $this->auth['info']['urls']['website'] = $userinfo['website'];
                if (!empty($userinfo->bio)) $this->auth['info']['description'] = $userinfo['bio'];

                /**
                 * NOTE:
                 * Pinterest's access_token have no explicit expiry, however, please do not assume your
                 * access_token is valid forever.
                 *
                 * Missing optional info values
                 * - email
                 */

                $this->callback();
            }
            else{
                $error = array(
                    'provider' => 'Pinterest',
                    'code' => 'access_token_error',
                    'message' => 'Failed when attempting to obtain access token',
                    'raw' => array(
                        'url' => $url,
                        'response' => $results,
                    )
                );

                $this->errorCallback($error);
            }
        }
        else{
            $error = array(
                'provider' => 'Pinterest',
                'code' => $_GET['error'],
                'reason' => $_GET['error_reason'],
                'message' => $_GET['error_description'],
                'raw' => $_GET
            );

            $this->errorCallback($error);
        }
    }

    /**
     * Queries Pinterest API for user info
     *
     * @param	integer	$uid
     * @param	string	$access_token
     * @return	array	Parsed JSON results
     */
    private function userinfo($access_token){

        $headers = null; // it will be rewrited in serverGet
        $userinfo = $this->serverGet('https://api.pinterest.com/v1/me/', array('access_token' => $access_token,'fields'=>'id,first_name,last_name,url,username,bio,created_at,counts,image'), null, $headers);

        if (!empty($userinfo)){
            $results = json_decode($userinfo,true);

            return $results['data'];
        }
        else{
            $error = array(
                'provider' => 'Pinterest',
                'code' => 'userinfo_error',
                'message' => 'Failed when attempting to query for user information',
                'raw' => array(
                    'response' => $userinfo,
                    'headers' => $headers
                )
            );
            $this->errorCallback($error);
        }
    }
}
?>
