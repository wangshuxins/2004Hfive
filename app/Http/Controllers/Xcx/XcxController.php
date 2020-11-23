<?php

namespace App\Http\Controllers\Xcx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Model\XcxUser;
use App\Model\ShopNav;
use App\Model\ShopCate;
use App\Model\Goods;
use App\Model\Brand;
class XcxController extends Controller
{
    public function openid(){
        $appid = 'wx38a0b89cb32b272b'; // 小程序APPID
        $secret = '58426119d67e249aa4e4c2f8082db5f0'; // 小程序secret
        $code=$_GET['code'];
        $post = request()->u;
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid=' . $appid . '&secret='.$secret.'&js_code='.$code.'&grant_type=authorization_code';
        $array = json_decode(file_get_contents($url),true);
        if(isset($array['errcode'])){
            $response = [
                'error_no'=>'100001',
                'error_msg'=>'登陆失败',
            ];
        }else{
            if(empty(XcxUser::where("open_id",$array['openid'])->first())){
                $openid = $array['openid'];
				$datas = [
				   'open_id'=>$openid,
				   'nickname'=>$post["nickName"],
					'gender'=>$post['gender'],
					'language'=>$post['language'],
					'city'=>$post['city'],
					'province'=>$post['province'],
					'country'=>$post['country'],
					'avatarUrl'=>$post['avatarUrl'],
					'type'=>3,
					'add_time'=>time(),
				];
                XcxUser::insert($datas);
            }
            $token = sha1($array['openid'].$array['session_key'].mt_rand(0,999999));
            $key = "wx_xcx_token".$token;
            Redis::set($key,time());
           
               $response = [
                   'error_no'=>'0',
                   'error_msg'=>'登陆成功',
                   'token'=>$token
               ];
        }
        return $response;
    }
    public function navigation(){
		
        $key = "slide";
        $slide = Redis::get($key);
        $slide = unserialize($slide);

        if(empty($slide)){
            $slide  = ShopNav::select("nav_url","nav_id","nav_detail","goods_id")->orderBy("nav_weight","desc")->where("is_show",0)->get()->toArray();
            $slides = serialize($slide);
            Redis::set($key,$slides);
        }
        Redis::expire($key,7200);
        return $slide;
    }
    public function cate(){
		//Redis::flushall();exit;
        $key = "cate";
        $shopcate = Redis::get($key);
        $shopcate = unserialize($shopcate);
        if(empty($shopcate)) {
            $where = [
                ["is_show", 1],
                ["is_nav_show", 1],
                ["is_del", 1],
                ["parent_id", 0]
            ];
            $shopcate = ShopCate::select("cate_id", "cate_name")->where($where)->get()->toArray();
            $shopcates = serialize($shopcate);
            Redis::set($key,$shopcates);
        }
        Redis::expire($key,7200);
        return $shopcate;
    }
    public function goods(){
		$cate_id=request()->cate_id;
		 if(!request()->get("page")){
            $page = 1;
        }else{
            $page = request()->get("page");
        }
			$res=ShopCate::select("cate_id")->where("parent_id",$cate_id)->get()->toArray();
			//dd($res);
			$arr=[];
			foreach($res as $k=>$v){
				foreach($v as $l=>$a){
					$arr[]=$a;
				}
			}
       
        //$key = "goods_".$page;
        //$goods = Redis::get($key);
        //$goods = unserialize($goods);
        if(empty($goods)) {
            $goods = Goods::where("shop_goods.is_del", 1)
                ->select("goods_id", "goods_name", "goods_img", "goods_price", "brand_name","goods_store")
                ->leftjoin("shop_brand", "shop_goods.brand_id", "=", "shop_brand.brand_id")
                ->orderBy("shop_goods.goods_id","asc")
				 ->whereIn("cate_id",$arr)
                ->paginate(5);
        $response=[
            'data'=>[
               'list'=>$goods->items()
            ]
        ];
          //$goodsx = serialize($goods);
           //Redis::set($key,$goodsx);
       }

       //Redis::expire($key,7200);
       return $response;
    }
    public function detail(){
		
      // Redis::flushall();exit;
        $goods_id = Request()->get("goods_id");
        $key = "detail_".$goods_id;
        $detail = Redis::get($key);
        $detail = unserialize($detail);
        if(empty($detail)){
            $detail =  Goods::select("goods_id","goods_imgs","goods_name","goods_price","goods_store","goods_desc")->where("goods_id",$goods_id)->where("is_del",1)->first()->toArray();
            $details = serialize($detail);
            Redis::set($key,$details);
        }
		$detail = [
		    "goods_imgs"=>explode(",",$detail["goods_imgs"]),
			"goods_name"=>$detail["goods_name"],
			"goods_price"=>$detail['goods_price'],
            "goods_store"=>$detail['goods_store'],
            "goods_desc"=>explode(",",$detail["goods_desc"]),
            "goods_id"=>$detail["goods_id"]
		];
        return $detail;
    }
}