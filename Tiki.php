<?php
namespace modules\tiki;                            //Make sure namespace is same structure with parent directory

use \classes\Auth as Auth;                          //For authentication internal user
use \classes\JSON as JSON;                          //For handling JSON in better way
use \classes\CustomHandlers as CustomHandlers;      //To get default response message
use \classes\Validation as Validation;              //To validate the string
use \classes\UniversalCache as UniversalCache;      //To cache the token
use \modules\flexibleconfig\FlexibleConfig as FlexibleConfig;      //To make login more flexible
use PDO;                                            //To connect with database

	/**
     * Example to create tiki module in reSlim
     *
     * @package    modules/tiki
     * @author     M ABD AZIZ ALFIAN <github.com/aalfiann>
     * @copyright  Copyright (c) 2018 M ABD AZIZ ALFIAN
     * @license    https://github.com/aalfiann/reSlim-modules-tiki/blob/master/LICENSE.md  MIT License
     */
    class Tiki {

        // database var
		protected $db,$sqlite;
		
		//base var
        protected $basepath,$baseurl,$basemod;

        //master var
        var $username,$token;

        //config var
        var $keyconfig = 'apitikilogin';
        var $login = 'test:123456';
        var $agecache = 300;

        //data var
        var $connote,$search;
		
		//multi language
		var $lang;
        
        //construct database object
        function __construct($db=null) {
			if (!empty($db)) $this->db = $db;
            $this->baseurl = (($this->isHttps())?'https://':'http://').$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']);
			$this->basepath = $_SERVER['DOCUMENT_ROOT'].dirname($_SERVER['PHP_SELF']);
            $this->basemod = dirname(__FILE__);
            $this->sqlite = $this->openSqlite();
            $this->login = $this->setLogin();
        }
        
        //Detect scheme host
        function isHttps() {
            $whitelist = array(
                '127.0.0.1',
                '::1'
            );
            
            if(!in_array($_SERVER['REMOTE_ADDR'], $whitelist)){
                if (!empty($_SERVER['HTTP_CF_VISITOR'])){
                    return isset($_SERVER['HTTPS']) ||
                    ($visitor = json_decode($_SERVER['HTTP_CF_VISITOR'])) &&
                    $visitor->scheme == 'https';
                } else {
                    return isset($_SERVER['HTTPS']);
                }
            } else {
                return 0;
            }            
        }

        //Get modules information
        public function viewInfo(){
            return file_get_contents($this->basemod.'/package.json');
        }


        //API TIKI===========================================

        private function getDataLogin(){
            $fc = new FlexibleConfig($this->db);
            return $fc->readConfig($this->keyconfig);
        }

        private function setLogin(){
            if(empty($this->getDataLogin())){
                $fc = new FlexibleConfig($this->db);
                $fc->insertConfig($this->keyconfig,$this->login,'tiki_module','Data login to access the Tiki web api.');
                return $this->getDataLogin();
            }
            return $this->getDataLogin();
        }

        private function openSqlite() {
			$dir = 'sqlite:'.$this->basemod.'/data_area.sqlite3';
            $pdo  = new PDO($dir);
	        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    	    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        	return $pdo;
        }

        private function limitRound($numToRound,$pointDecimal=0.5){
            $value = 0;
            if(($numToRound - floor($numToRound)) >= $pointDecimal){
                $value = 1;
            } else {
                $value = 0;
            }
            return floor($numToRound + $value);
        }

        private function curlPost($url,$post_array,$token=""){
        
            if(empty($url)){ return false;}
            //build query
            if (!empty($post_array)) $fields_string=json_encode($post_array);
        
            //open connection
            $ch = curl_init();
        
            ////curl parameter set the url, number of POST vars, POST data
            curl_setopt($ch,CURLOPT_URL,$url);
            curl_setopt($ch,CURLOPT_POST,1);
            if (!empty($post_array)) curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
            if (!empty($token)){
                $header = ['Content-Type:application/json','x-access-token:'.$token];
            } else {
                $header = ['Content-Type:application/json'];
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        
            //execute post
            $result = curl_exec($ch);
        
            //close connection
            curl_close($ch);
        
            return $result;
        }

        private function doLogin(){
            if(!empty($this->login)){
                $data = explode(':',$this->login);
                if(!empty($data[0]) && !empty($data[1])){
                    return [
                        'username' => $data[0],
                        'password' => $data[1]
                    ];
                }
            }
            return [];
        }
        
        private function getToken(){
            $datalogin = $this->doLogin();
            if (!empty($datalogin)){
                if (UniversalCache::isCached('tiki-token',$this->agecache)){
                    $datajson = JSON::decode(UniversalCache::loadCache('tiki-token'));
                    return $datajson->value;
                } else {
                    $datajson = JSON::decode($this->curlPost('http://apis.mytiki.net:8321/user/auth',$datalogin));
                    if (!empty($datajson)){
                        if ($datajson->status == 200){
                            $datatoken = $datajson->response->token;
                            UniversalCache::writeCache('tiki-token',$datatoken);
                            return $datatoken;
                        }
                    }
                }
            }
            return ''; 
        }

        public function infoConnote(){
            if (!empty($this->connote)){
                if (!empty($token = $this->getToken())){
                    $datajson = JSON::decode($this->curlPost('http://apis.mytiki.net:8321/connote/info',['cnno'=>$this->connote],$token));
                    if (!empty($datajson)){
                        if (!empty($datajson->response)){
                            $data = [
                                'result' => $datajson->response,
                                'status' => 'success',
                                'code' => 'RS501',
                                'message' => CustomHandlers::getreSlimMessage('RS501',$this->lang)
                            ];
                        } else {
                            $data = [
                                'status' => 'error',
                                'code' => 'RS601',
                                'message' => CustomHandlers::getreSlimMessage('RS601',$this->lang)
                            ];
                        }
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 'RS202',
                            'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
                        ];
                    }
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 'RS202',
                        'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
                    ];
                }
            } else {
                $data = [
                    'status' => 'error',
                    'code' => 'RS801',
                    'message' => CustomHandlers::getreSlimMessage('RS801',$this->lang)
                ];
            }

            return JSON::encode($data,true); 
        }

        public function historyConnote(){
            if (!empty($this->connote)){
                if (!empty($token = $this->getToken())){
                    $datajson = JSON::decode($this->curlPost('http://apis.mytiki.net:8321/connote/history',['cnno'=>$this->connote],$token));
                    if (!empty($datajson)){
                        if (!empty($datajson->response)){
                            $data = [
                                'result' => $datajson->response,
                                'status' => 'success',
                                'code' => 'RS501',
                                'message' => CustomHandlers::getreSlimMessage('RS501',$this->lang)
                            ];
                        } else {
                            $data = [
                                'status' => 'error',
                                'code' => 'RS601',
                                'message' => CustomHandlers::getreSlimMessage('RS601',$this->lang)
                            ];
                        }
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 'RS202',
                            'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
                        ];
                    }
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 'RS202',
                        'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
                    ];
                }
            } else {
                $data = [
                    'status' => 'error',
                    'code' => 'RS801',
                    'message' => CustomHandlers::getreSlimMessage('RS801',$this->lang)
                ];
            }

            return JSON::encode($data,true); 
        }

        public function statusCode(){
            if (!empty($token = $this->getToken())){
                $datajson = JSON::decode($this->curlPost('http://apis.mytiki.net:8321/connote/statuscode',"",$token));
                if (!empty($datajson)){
                    if (!empty($datajson->response)){
                        $data = [
                            'result' => $datajson->response,
                            'status' => 'success',
                            'code' => 'RS501',
                            'message' => CustomHandlers::getreSlimMessage('RS501',$this->lang)
                        ];
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 'RS601',
                            'message' => CustomHandlers::getreSlimMessage('RS601',$this->lang)
                        ];
                    }                        
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 'RS202',
                        'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
                    ];
                }
            } else {
                $data = [
                    'status' => 'error',
                    'code' => 'RS202',
                    'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
                ];
            }
            return JSON::encode($data,true); 
        }

        public function product(){
            if (!empty($this->origin) && !empty($this->destination) && !empty($this->weight)){
                if (!empty($token = $this->getToken())){
                    $datajson = JSON::decode($this->curlPost('http://apis.mytiki.net:8321/tariff/product',['orig'=>$this->origin,'dest'=>$this->destination,'weight'=>$this->limitRound($this->weight,0.3)],$token));
                    if (!empty($datajson)){
                        if (!empty($datajson->response)){
                            $data = [
                                'result' => $datajson->response,
                                'status' => 'success',
                                'code' => 'RS501',
                                'message' => CustomHandlers::getreSlimMessage('RS501',$this->lang)
                            ];
                        } else {
                            $data = [
                                'status' => 'error',
                                'code' => 'RS601',
                                'message' => CustomHandlers::getreSlimMessage('RS601',$this->lang)
                            ];
                        }
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 'RS202',
                            'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
                        ];
                    }
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 'RS202',
                        'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
                    ];
                }
            } else {
                $data = [
                    'status' => 'error',
                    'code' => 'RS801',
                    'message' => CustomHandlers::getreSlimMessage('RS801',$this->lang)
                ];
            }

            return JSON::encode($data,true); 
        }

        public function token(){
            if (!empty($token = $this->getToken())){
                $data = [
                    'status' => 'success',
                    'token' => $token,
                    'code' => 'RS301',
                    'message' => CustomHandlers::getreSlimMessage('RS301',$this->lang)
                ];
            } else {
                $data = [
                    'status' => 'error',
                    'code' => 'RS202',
                    'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
                ];
            }
            return JSON::encode($data,true); 
        }

        public function getArea(){
            $search = "$this->search%";
			$sql = "SELECT id,city_id,province_id,citycounty_id,sub_dist,dist,city_county_type,city_county,province,zip_code,tariff_code
					FROM area
					WHERE dist like :search 
                    OR sub_dist like :search
                    OR zip_code like :search
                    OR tariff_code like :search
                    ORDER BY dist ASC
                    LIMIT 50;";
				
			$stmt = $this->sqlite->prepare($sql);	
			$stmt->bindParam(':search', $search, PDO::PARAM_STR);

			if ($stmt->execute()) {	
				$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    	        if ($result && count($result)){
					$data = [
                        'result' => $result,
                        'status' => 'success',
                        'code' => 'RS501',
                        'message' => CustomHandlers::getreSlimMessage('RS501',$this->lang)
                    ];
		        } else {
        			$data = [
                        'status' => 'error',
                        'code' => 'RS601',
                        'message' => CustomHandlers::getreSlimMessage('RS601',$this->lang)
                    ];
	    	    }  	   	
			} else {
				$data = [
                    'status' => 'error',
                    'code' => 'RS202',
                    'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
                ];
			}
			return JSON::encode($data,true);
			$this->sqlite = null;
		}

    }    