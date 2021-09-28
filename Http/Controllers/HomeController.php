<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Categories;
use App\Regions;
use App\Adverts;
use App\Places;
use DB;

class HomeController extends Controller {
		    
    public function ShowHomePage() {

    if (Auth::check()) {        

        $items = DB::table("adverts as adv")->select(			
			"urls.url",
            "adv.id", 
            "adv.title", 
            "adv.price", 
            "adv.startDate",            
            "kz_region.name as region_name",
            "kz_city.name as city_name",
            "adex_color.startDate as colorStartDate",		                            
            "adex_color.finishDate as colorFinishDate",		                            
            "adex_srochno.startDate as srochnoStartDate",		                            
            "adex_srochno.finishDate as srochnoFinishDate",		                            
            "adex_top.startDate as topStartDate",		                            
            "adex_top.finishDate as topFinishDate",		                            			                            			                            
            DB::raw("(SELECT COUNT(*) FROM adverts WHERE NOW() BETWEEN startDate AND finishDate AND id=adv.id) as expired"),                        
            DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
            DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
            DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"))
			->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
            ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			
            ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )			                    
			->join("urls", "adv.id", "=", "urls.advert_id" )
			->join("kz_region", "adv.region_id", "=", "kz_region.region_id" )
			->join("kz_city", "adv.city_id", "=", "kz_city.city_id" )			
            ->orderBy("adv.id", "desc")
            ->where("user_id", Auth::id())            
            ->paginate(10)
            ->onEachSide(1);

            \Debugbar::info($items);

        $userName = Auth::user()->name;

        return view("home")
        ->with("items", $items)
        ->with("title", "Личный кабинет ".$userName)
        ->with("description", "Личный кабинет пользователя ".$userName)
        ->with("keywords", "Личный кабинет, ".$userName);    
        }                    
        else 
            return redirect('/login');
    }					
}