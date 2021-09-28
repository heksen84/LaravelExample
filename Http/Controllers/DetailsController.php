<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\Petrovich;
use App\Helpers\Common;
use App\Adverts;
use App\Images;
use App\Urls;
use App\Socials;

class DetailsController extends Controller {

    private $raw_engine_type = "CASE 
        WHEN transport.engine_type=0 THEN 'бензин' 
        WHEN transport.engine_type=1 THEN 'дизель' 
        WHEN transport.engine_type=2 THEN 'газ-бензин'
        WHEN transport.engine_type=3 THEN 'газ'
        WHEN transport.engine_type=4 THEN 'гибрид'
        WHEN transport.engine_type=5 THEN 'электричество'
        ELSE '-' END as engine_type";

        // --------------------------------------------------
        // детали объявления
        // --------------------------------------------------
        public function getDetails(Request $request, $title) {

            \Debugbar::info("VIEW: ".$request->view);
            
            // получаю данные url объявления по заголовку объявления
            $urlData = Urls::select("advert_id")->where( "url", $title )->get();
                        
            if (!count($urlData))
                abort(404);            
            
                // получаю id объявления
                $id = $urlData[0]->advert_id;

                // получаю данные объявления по его id
                $advertData = Adverts::select("category_id", "subcategory_id")->where( "id", $id )->where( "public", true )->limit(1)->get();
                        
                if (!count($advertData))
                  abort(404);             		        

                \Debugbar::info("-------------------");
                \Debugbar::info($advertData);
                \Debugbar::info("-------------------");

                \Debugbar::info("Источник: ".$request->source);

                // Выборка на основании владельца
                if ($request->source && $request->source === 'owner') {
                    \Debugbar::info("Владелец");                    
                    $periodSampleSql = "adv.public = true";
                } 
                else
                    $periodSampleSql = "NOW() BETWEEN adv.startDate AND adv.finishDate AND adv.public = true";

                // легковое авто
                if ($advertData[0]->category_id === 1 && $advertData[0]->subcategory_id === 1) {  
                    
                \Debugbar::info("легковое авто");

                    $advert = DB::table("adverts as adv")->select(
                            "car_model.id_car_mark",
                            "socials.insta_login",
                            "adv.region_id",
                            "adv.city_id",                                 
                            "adv.category_id",
                            "adv.subcategory_id",
                            "adv.startDate",
                            "adv.id", 
                            "adv.title", 
                            "adv.text", 
                            "adv.price", 
                            "adv.phone", 
                            "adv.coord_lat", 
                            "adv.coord_lon", 
                            "transport.type", 
                            "car_mark.name as car_name", 
                            "car_model.name as car_model", 
                            "transport.year", 
                            "transport.mileage",                            
                            DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
                            DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
                            DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"),
                            "adex_color.startDate as colorStartDate",		                            
                            "adex_color.finishDate as colorFinishDate",		                            
                            "adex_srochno.startDate as srochnoStartDate",		                            
                            "adex_srochno.finishDate as srochnoFinishDate",		                            
                            "adex_top.startDate as topStartDate",		                            
                            "adex_top.finishDate as topFinishDate",
                            "kz_region.url as region_url",
                            "kz_city.url as city_url",		                            			                            			                            
                            DB::raw("CASE WHEN transport.steering_position=0 THEN 'слева' ELSE 'справа' END as steering_position"),                            
                            DB::raw($this->raw_engine_type),
                            DB::raw("CASE WHEN transport.customs=1 THEN 'да' ELSE 'нет' END as customs"),                                
                            DB::raw("`kz_region`.`name` AS region_name, `kz_city`.`name` AS city_name"),
                            DB::raw("`kz_region`.`url` AS region_url, `kz_city`.`url` AS city_url"),
                            "categories.name as category_name",
                            "categories.url as category_url",
                            "subcats.name as subcat_name",
                            "subcats.url as subcat_url")
                            ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
                            ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			
                            ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )			                    
                            ->leftJoin("socials", "adv.city_id" , "=" , "socials.place_id" )
                            ->join("categories", "adv.category_id" , "=" , "categories.id" )
                            ->join("subcats", "adv.subcategory_id" , "=" , "subcats.id" )
                            ->join("kz_region", "adv.region_id" , "=" , "kz_region.region_id" )                
                            ->join("kz_city", "adv.city_id" , "=" , "kz_city.city_id" )                
                            ->join("sub_transport as transport", "adv.inner_id" , "=" , "transport.id" )                
                            ->join("car_mark", "car_mark.id_car_mark" , "=" , "transport.mark" )  // toyota                                                                              
                            ->join("car_model", "car_model.id_car_model", "transport.model" )                                                                      
                            ->where( "adv.id", $id )                                
                            ->whereRaw($periodSampleSql)
                            ->limit(1)
                            ->get();                            
                }

                // грузовое авто
                if ($advertData[0]->category_id === 1 && $advertData[0]->subcategory_id === 2) {

                \Debugbar::info("грузовое авто");

                    $advert = DB::table("adverts as adv")->select(
                            "socials.insta_login",
                            "adv.region_id",
                            "adv.city_id",                                 
                            "adv.category_id",
                            "adv.subcategory_id",
                            "adv.startDate",
                            "adv.id", 
                            "adv.title", 
                            "adv.text", 
                            "adv.price", 
                            "adv.phone", 
                            "adv.coord_lat", 
                            "adv.coord_lon", 
                            "transport.type",                                
                            "transport.year", 
                            "transport.mileage",
                            DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
                            DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
                            DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"),
                            "adex_color.startDate as colorStartDate",		                            
                            "adex_color.finishDate as colorFinishDate",		                            
                            "adex_srochno.startDate as srochnoStartDate",		                            
                            "adex_srochno.finishDate as srochnoFinishDate",		                            
                            "adex_top.startDate as topStartDate",		                            
                            "adex_top.finishDate as topFinishDate",		                            			                            			                                    
                            "kz_region.url as region_url",
                            "kz_city.url as city_url",
                            DB::raw("CASE WHEN transport.steering_position=0 THEN 'слева' ELSE 'справа' END as steering_position"),                            
                            DB::raw($this->raw_engine_type),
                            DB::raw("CASE WHEN transport.customs=1 THEN 'да' ELSE 'нет' END as customs"),                                
                            DB::raw("`kz_region`.`name` AS region_name, `kz_city`.`name` AS city_name"),
                            DB::raw("`kz_region`.`url` AS region_url, `kz_city`.`url` AS city_url"),
                            "categories.name as category_name",
                            "categories.url as category_url",
                            "subcats.name as subcat_name",
                            "subcats.url as subcat_url")
                            ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
                            ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			
                            ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )
                            ->leftJoin("socials", "adv.city_id" , "=" , "socials.place_id" )			                    
                            ->join("categories", "adv.category_id" , "=" , "categories.id" )
                            ->join("subcats", "adv.subcategory_id" , "=" , "subcats.id" )
                            ->join("kz_region", "adv.region_id" , "=" , "kz_region.region_id" )                
                            ->join("kz_city", "adv.city_id" , "=" , "kz_city.city_id" )                
                            ->join("sub_transport as transport", "adv.inner_id" , "=" , "transport.id" )                
                            ->where( "adv.id", $id )                                
                            ->whereRaw($periodSampleSql)                        
                            ->limit(1)
                            ->get();                                
                }

                // мототехника
                if ($advertData[0]->category_id === 1 && $advertData[0]->subcategory_id === 3) {

                            $advert = DB::table("adverts as adv")->select(
                                "socials.insta_login",
                                "adv.region_id",
                                "adv.city_id",
                                "adv.category_id",
                                "adv.subcategory_id",
                                "adv.startDate",                                
                                "adv.id", 
                                "adv.title", 
                                "adv.text", 
                                "adv.price", 
                                "adv.phone", 
                                "adv.coord_lat", 
                                "adv.coord_lon",
                                "categories.name as category_name",
                                "categories.url as category_url",
                                "subcats.name as subcat_name",
                                "subcats.url as subcat_url",
                                "kz_region.url as region_url",
                                "kz_city.url as city_url",
                                DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
                                DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
                                DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"),
                                "adex_color.startDate as colorStartDate",		                            
                                "adex_color.finishDate as colorFinishDate",		                            
                                "adex_srochno.startDate as srochnoStartDate",		                            
                                "adex_srochno.finishDate as srochnoFinishDate",		                            
                                "adex_top.startDate as topStartDate",		                            
                                "adex_top.finishDate as topFinishDate",		                            			                            			                                        
                                DB::raw("kz_region.name AS region_name, kz_city.name AS city_name"),
                                DB::raw("`kz_region`.`url` AS region_url, `kz_city`.`url` AS city_url"))
                                ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
                                ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			
                                ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )
                                ->leftJoin("socials", "adv.city_id" , "=" , "socials.place_id" )			                    
                                ->join("categories", "adv.category_id" , "=" , "categories.id" )
                                ->join("subcats", "adv.subcategory_id" , "=" , "subcats.id" )
                                ->join("kz_region", "adv.region_id" , "=" , "kz_region.region_id" )                
                                ->join("kz_city", "adv.city_id" , "=" , "kz_city.city_id" )                
                                ->where( "adv.id", $id )
                                ->whereRaw($periodSampleSql)
                                ->limit(1)
                                ->get();
                }

                // спецтехника
                if ($advertData[0]->category_id === 1 && $advertData[0]->subcategory_id === 4) {
                    
                            $advert = DB::table("adverts as adv")->select(
                                "socials.insta_login",
                                "adv.region_id",
                                "adv.city_id",
                                "adv.category_id",
                                "adv.subcategory_id",                            
                                "adv.startDate",                                    
                                "adv.id", 
                                "adv.title", 
                                "adv.text", 
                                "adv.price", 
                                "adv.phone", 
                                "adv.coord_lat", 
                                "adv.coord_lon",
                                "categories.name as category_name",
                                "categories.url as category_url",
                                "subcats.name as subcat_name",
                                "subcats.url as subcat_url",
                                "kz_region.url as region_url",
                                "kz_city.url as city_url",
                                DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
                                DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
                                DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"),
                                "adex_color.startDate as colorStartDate",		                            
                                "adex_color.finishDate as colorFinishDate",		                            
                                "adex_srochno.startDate as srochnoStartDate",		                            
                                "adex_srochno.finishDate as srochnoFinishDate",		                            
                                "adex_top.startDate as topStartDate",		                            
                                "adex_top.finishDate as topFinishDate",		                            			                            			                                    
                                DB::raw("kz_region.name AS region_name, kz_city.name AS city_name"),
                                DB::raw("`kz_region`.`url` AS region_url, `kz_city`.`url` AS city_url"))                            
                                ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
                                ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			
                                ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )
                                ->leftJoin("socials", "adv.city_id" , "=" , "socials.place_id" )			                    
                                ->join("categories", "adv.category_id" , "=" , "categories.id" )
                                ->join("subcats", "adv.subcategory_id" , "=" , "subcats.id" )
                                ->join("kz_region", "adv.region_id" , "=" , "kz_region.region_id" )                
                                ->join("kz_city", "adv.city_id" , "=" , "kz_city.city_id" )                
                                ->where( "adv.id", $id )
                                ->whereRaw($periodSampleSql)                        
                                ->limit(1)
                                ->get();
                }

            // ретро авто
            if ($advertData[0]->category_id === 1 && $advertData[0]->subcategory_id === 5) {

                    $advert = DB::table("adverts as adv")->select(
                            "socials.insta_login",
                            "adv.region_id",
                            "adv.city_id",                                 
                            "adv.category_id",
                            "adv.subcategory_id",
                            "adv.startDate",
                            "adv.id", 
                            "adv.title", 
                            "adv.text", 
                            "adv.price", 
                            "adv.phone", 
                            "adv.coord_lat", 
                            "adv.coord_lon", 
                            "transport.type",                                
                            "transport.year", 
                            "transport.mileage",
                            "categories.name as category_name",
                            "categories.url as category_url",
                            "subcats.name as subcat_name",
                            "subcats.url as subcat_url",
                            "kz_region.url as region_url",
                            "kz_city.url as city_url",
                            DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
                            DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
                            DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"),
                            "adex_color.startDate as colorStartDate",		                            
                            "adex_color.finishDate as colorFinishDate",		                            
                            "adex_srochno.startDate as srochnoStartDate",		                            
                            "adex_srochno.finishDate as srochnoFinishDate",		                            
                            "adex_top.startDate as topStartDate",		                            
                            "adex_top.finishDate as topFinishDate",		                            			                            			                                
                            DB::raw("CASE WHEN transport.steering_position=0 THEN 'слева' ELSE 'справа' END as steering_position"),                            
                            DB::raw($this->raw_engine_type),
                            DB::raw("CASE WHEN transport.customs=1 THEN 'да' ELSE 'нет' END as customs"),                                
                            DB::raw("`kz_region`.`name` AS region_name, `kz_city`.`name` AS city_name"),
                            DB::raw("`kz_region`.`url` AS region_url, `kz_city`.`url` AS city_url"))
                            ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
                            ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )                    
                            ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )
                            ->leftJoin("socials", "adv.city_id" , "=" , "socials.place_id" )			                        
                            ->join("categories", "adv.category_id" , "=" , "categories.id" )
                            ->join("subcats", "adv.subcategory_id" , "=" , "subcats.id" )
                            ->join("kz_region", "adv.region_id" , "=" , "kz_region.region_id" )                
                            ->join("kz_city", "adv.city_id" , "=" , "kz_city.city_id" )                
                            ->join("sub_transport as transport", "adv.inner_id" , "=" , "transport.id" )                
                            ->where( "adv.id", $id )                                
                            ->whereRaw($periodSampleSql)                        
                            ->limit(1)
                            ->get();                                
            }
            
            // выборка для остального траспорта
            if ($advertData[0]->category_id === 1 && $advertData[0]->subcategory_id > 5) {                        

                    $advert = DB::table("adverts as adv")->select(
                        "socials.insta_login",
                        "adv.region_id",
                        "adv.city_id",                                 
                        "adv.category_id",
                        "adv.subcategory_id",        
                        "adv.startDate",                         
                        "adv.id", 
                        "adv.title", 
                        "adv.text", 
                        "adv.price", 
                        "adv.phone", 
                        "adv.coord_lat", 
                        "adv.coord_lon",
                        "categories.name as category_name",
                        "categories.url as category_url",
                        "subcats.name as subcat_name",
                        "subcats.url as subcat_url",
                        "kz_region.url as region_url",
                        "kz_city.url as city_url",
                        DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
                        DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
                        DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"),
                        "adex_color.startDate as colorStartDate",		                            
                        "adex_color.finishDate as colorFinishDate",		                            
                        "adex_srochno.startDate as srochnoStartDate",		                            
                        "adex_srochno.finishDate as srochnoFinishDate",		                            
                        "adex_top.startDate as topStartDate",		                            
                        "adex_top.finishDate as topFinishDate",		                            			                            			                                
                        DB::raw("kz_region.name AS region_name, kz_city.name AS city_name"),
                        DB::raw("`kz_region`.`url` AS region_url, `kz_city`.`url` AS city_url"))
                        ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
                        ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			        
                        ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )
                        ->leftJoin("socials", "adv.city_id" , "=" , "socials.place_id" )			                                
                        ->join("categories", "adv.category_id" , "=" , "categories.id" )
                        ->join("subcats", "adv.subcategory_id" , "=" , "subcats.id" )
                        ->join("kz_region", "adv.region_id" , "=" , "kz_region.region_id" )                
                        ->join("kz_city", "adv.city_id" , "=" , "kz_city.city_id" )                    
                        ->where( "adv.id", $id )
                        ->whereRaw($periodSampleSql)                        
                        ->limit(1)
                        ->get();
            }
           
            // квартира
            if ($advertData[0]->category_id === 2 && $advertData[0]->subcategory_id === 9) {

                \Debugbar::info("квартира");
                                    
                    $advert = DB::table("adverts as adv")->select(
                        "socials.insta_login",
                        "adv.region_id",
                        "adv.city_id",                                 
                        "adv.category_id",
                        "adv.subcategory_id",
                        "adv.startDate",
                        "adv.id", 
                        "adv.title", 
                        "adv.text", 
                        "adv.price", 
                        "adv.phone", 
                        "adv.coord_lat", 
                        "adv.coord_lon",
                        "realestate.property_type",
                        "realestate.floor",
                        "realestate.floors_house",
                        "realestate.rooms",
                        "realestate.area",
                        DB::raw("CASE WHEN realestate.ownership=0 THEN 'собственник' ELSE 'посредник' END as ownership"),
                        DB::raw("CASE WHEN realestate.kind_of_object=0 THEN 'вторичка' ELSE 'новостройка' END as kind_of_object"),                        
                        "categories.name as category_name",
                        "categories.url as category_url",
                        "subcats.name as subcat_name",
                        "subcats.url as subcat_url",
                        DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
                        DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
                        DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"),
                        "adex_color.startDate as colorStartDate",		                            
                        "adex_color.finishDate as colorFinishDate",		                            
                        "adex_srochno.startDate as srochnoStartDate",		                            
                        "adex_srochno.finishDate as srochnoFinishDate",		                            
                        "adex_top.startDate as topStartDate",		                            
                        "adex_top.finishDate as topFinishDate",
                        "kz_region.url as region_url",
                        "kz_city.url as city_url",		                            			                            			                            
                        DB::raw("CASE WHEN realestate.ownership=0 THEN 'собственник' ELSE 'посредник' END as ownership"),                        
                        DB::raw("`kz_region`.`name` AS region_name, `kz_city`.`name` AS city_name"),
                        DB::raw("`kz_region`.`url` AS region_url, `kz_city`.`url` AS city_url"))
                        ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
                        ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			            
                        ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )
                        ->leftJoin("socials", "adv.city_id" , "=" , "socials.place_id" )			                            
                        ->join("categories", "adv.category_id" , "=" , "categories.id" )
                        ->join("subcats", "adv.subcategory_id" , "=" , "subcats.id" )
                        ->join("kz_region", "adv.region_id" , "=" , "kz_region.region_id" )                
                        ->join("kz_city", "adv.city_id" , "=" , "kz_city.city_id" )                                
                        ->join("sub_realestate as realestate", "adv.inner_id" , "=" , "realestate.id" )                                
                        ->where( "adv.id", $id )                                
                        ->whereRaw($periodSampleSql)                        
                        ->limit(1)
                        ->get();                                                        
            }
            
            // комната
            if ($advertData[0]->category_id === 2 && $advertData[0]->subcategory_id === 10) {
                    
                $advert = DB::table("adverts as adv")->select(
                    "socials.insta_login",
                    "adv.region_id",
                    "adv.city_id",                                 
                    "adv.category_id",
                    "adv.subcategory_id",
                    "adv.startDate",
                    "adv.id", 
                    "adv.title", 
                    "adv.text", 
                    "adv.price", 
                    "adv.phone", 
                    "adv.coord_lat", 
                    "adv.coord_lon",
                    "realestate.property_type",
                    "realestate.floor",
                    "realestate.floors_house",
                    "realestate.area",
                    "categories.name as category_name",
                    "categories.url as category_url",
                    "subcats.name as subcat_name",
                    "subcats.url as subcat_url",
                    DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
                    DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
                    DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"),
                    "adex_color.startDate as colorStartDate",		                            
                    "adex_color.finishDate as colorFinishDate",		                            
                    "adex_srochno.startDate as srochnoStartDate",		                            
                    "adex_srochno.finishDate as srochnoFinishDate",		                            
                    "adex_top.startDate as topStartDate",		                            
                    "adex_top.finishDate as topFinishDate",
                    "kz_region.url as region_url",
                    "kz_city.url as city_url",		                            			                            			                            
                    DB::raw("CASE WHEN realestate.ownership=0 THEN 'собственник' ELSE 'посредник' END as ownership"),                        
                    DB::raw("`kz_region`.`name` AS region_name, `kz_city`.`name` AS city_name"),
                    DB::raw("`kz_region`.`url` AS region_url, `kz_city`.`url` AS city_url"))
                    ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
                    ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			            
                    ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )
                    ->leftJoin("socials", "adv.city_id" , "=" , "socials.place_id" )			                            
                    ->join("categories", "adv.category_id" , "=" , "categories.id" )
                    ->join("subcats", "adv.subcategory_id" , "=" , "subcats.id" )
                    ->join("kz_region", "adv.region_id" , "=" , "kz_region.region_id" )                
                    ->join("kz_city", "adv.city_id" , "=" , "kz_city.city_id" )                                
                    ->join("sub_realestate as realestate", "adv.inner_id" , "=" , "realestate.id" )                                
                    ->where( "adv.id", $id )                                
                    ->whereRaw($periodSampleSql)                        
                    ->limit(1)
                    ->get();                                                        
            }
            // дом, дача, коттедж
            if ($advertData[0]->category_id === 2 && $advertData[0]->subcategory_id === 11) {
                    
                $advert = DB::table("adverts as adv")->select(
                    "socials.insta_login",
                    "adv.region_id",
                    "adv.city_id",                                 
                    "adv.category_id",
                    "adv.subcategory_id",
                    "adv.startDate",
                    "adv.id", 
                    "adv.title", 
                    "adv.text", 
                    "adv.price", 
                    "adv.phone", 
                    "adv.coord_lat", 
                    "adv.coord_lon",
                    "realestate.property_type",                        
                    "realestate.rooms",
                    "realestate.area",
                    DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
                    DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
                    DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"),
                    "adex_color.startDate as colorStartDate",		                            
                    "adex_color.finishDate as colorFinishDate",		                            
                    "adex_srochno.startDate as srochnoStartDate",		                            
                    "adex_srochno.finishDate as srochnoFinishDate",		                            
                    "adex_top.startDate as topStartDate",		                            
                    "adex_top.finishDate as topFinishDate",
                    "kz_region.url as region_url",
                    "kz_city.url as city_url",		                            			                            			                            
                    DB::raw("CASE WHEN realestate.ownership=0 THEN 'собственник' ELSE 'посредник' END as ownership"),
                    DB::raw("CASE WHEN realestate.kind_of_object=0 THEN 'вторичка' ELSE 'новостройка' END as kind_of_object"),
                    DB::raw("CASE 
                    WHEN realestate.type_of_building=0 THEN 'дом' 
                    WHEN realestate.type_of_building=1 THEN 'дача' 
                    WHEN realestate.type_of_building=2 THEN 'коттедж' 
                    ELSE '-' 
                    END as type_of_building"),
                    "categories.name as category_name",
                    "categories.url as category_url",
                    "subcats.name as subcat_name",
                    "subcats.url as subcat_url",   
                    DB::raw("`kz_region`.`name` AS region_name, `kz_city`.`name` AS city_name"),
                    DB::raw("`kz_region`.`url` AS region_url, `kz_city`.`url` AS city_url"))
                    ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
                    ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			            
                    ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )
                    ->leftJoin("socials", "adv.city_id" , "=" , "socials.place_id" )
                    ->join("categories", "adv.category_id" , "=" , "categories.id" )
                    ->join("subcats", "adv.subcategory_id" , "=" , "subcats.id" )
                    ->join("kz_region", "adv.region_id" , "=" , "kz_region.region_id" )                
                    ->join("kz_city", "adv.city_id" , "=" , "kz_city.city_id" )                                
                    ->join("sub_realestate as realestate", "adv.inner_id" , "=" , "realestate.id" )                                
                    ->where( "adv.id", $id )                                
                    ->whereRaw($periodSampleSql)                        
                    ->limit(1)
                    ->get();                                                        
            }
            // земельный участок
            if ($advertData[0]->category_id === 2 && $advertData[0]->subcategory_id === 12) {
                    
                $advert = DB::table("adverts as adv")->select(
                    "socials.insta_login",
                    "adv.region_id",
                    "adv.city_id",                                 
                    "adv.category_id",
                    "adv.subcategory_id",
                    "adv.startDate",
                    "adv.id", 
                    "adv.title", 
                    "adv.text", 
                    "adv.price", 
                    "adv.phone", 
                    "adv.coord_lat", 
                    "adv.coord_lon",
                    "realestate.area",
                    "categories.name as category_name",
                    "categories.url as category_url",
                    "subcats.name as subcat_name",
                    "subcats.url as subcat_url",
                    "kz_region.url as region_url",
                    "kz_city.url as city_url",
                    DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
                    DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
                    DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"),
                    "adex_color.startDate as colorStartDate",		                            
                    "adex_color.finishDate as colorFinishDate",		                            
                    "adex_srochno.startDate as srochnoStartDate",		                            
                    "adex_srochno.finishDate as srochnoFinishDate",		                            
                    "adex_top.startDate as topStartDate",		                            
                    "adex_top.finishDate as topFinishDate",		                            			                            			                            
                    DB::raw("CASE WHEN realestate.ownership=0 THEN 'собственник' ELSE 'посредник' END as ownership"),                        
                    DB::raw("`kz_region`.`name` AS region_name, `kz_city`.`name` AS city_name"),
                    DB::raw("`kz_region`.`url` AS region_url, `kz_city`.`url` AS city_url"))
                    ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
                    ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			            
                    ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )
                    ->leftJoin("socials", "adv.city_id" , "=" , "socials.place_id" )		                            
                    ->join("categories", "adv.category_id" , "=" , "categories.id" )
                    ->join("subcats", "adv.subcategory_id" , "=" , "subcats.id" )
                    ->join("kz_region", "adv.region_id" , "=" , "kz_region.region_id" )                
                    ->join("kz_city", "adv.city_id" , "=" , "kz_city.city_id" )                                
                    ->join("sub_realestate as realestate", "adv.inner_id" , "=" , "realestate.id" )                                
                    ->where( "adv.id", $id )                                
                    ->whereRaw($periodSampleSql)                        
                    ->limit(1)
                    ->get();                                                        
            }
            // гараж или машиноместо
            if ($advertData[0]->category_id === 2 && $advertData[0]->subcategory_id === 13) {
                    
                $advert = DB::table("adverts as adv")->select(
                    "socials.insta_login",
                    "adv.region_id",
                    "adv.city_id",                                 
                    "adv.category_id",
                    "adv.subcategory_id",
                    "adv.startDate",
                    "adv.id", 
                    "adv.title", 
                    "adv.text", 
                    "adv.price", 
                    "adv.phone", 
                    "adv.coord_lat", 
                    "adv.coord_lon",                        
                    "realestate.area",
                    "categories.name as category_name",
                    "categories.url as category_url",
                    "subcats.name as subcat_name",
                    "subcats.url as subcat_url",
                    "kz_region.url as region_url",
                    "kz_city.url as city_url",
                    DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
                    DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
                    DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"),
                    "adex_color.startDate as colorStartDate",		                            
                    "adex_color.finishDate as colorFinishDate",		                            
                    "adex_srochno.startDate as srochnoStartDate",		                            
                    "adex_srochno.finishDate as srochnoFinishDate",		                            
                    "adex_top.startDate as topStartDate",		                            
                    "adex_top.finishDate as topFinishDate",		                            			                            			                            
                    DB::raw("CASE WHEN realestate.ownership=0 THEN 'собственник' ELSE 'посредник' END as ownership"),                        
                    DB::raw("`kz_region`.`name` AS region_name, `kz_city`.`name` AS city_name"),
                    DB::raw("`kz_region`.`url` AS region_url, `kz_city`.`url` AS city_url"))
                    ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
                    ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			            
                    ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )
                    ->leftJoin("socials", "adv.city_id" , "=" , "socials.place_id" )		                            
                    ->join("categories", "adv.category_id" , "=" , "categories.id" )
                    ->join("subcats", "adv.subcategory_id" , "=" , "subcats.id" )
                    ->join("kz_region", "adv.region_id" , "=" , "kz_region.region_id" )                
                    ->join("kz_city", "adv.city_id" , "=" , "kz_city.city_id" )                                
                    ->join("sub_realestate as realestate", "adv.inner_id" , "=" , "realestate.id" )                                
                    ->where( "adv.id", $id )                                
                    ->whereRaw($periodSampleSql)                        
                    ->limit(1)
                    ->get();                                                        
            }
            // коммерческая недвижимость
            if ($advertData[0]->category_id === 2 && $advertData[0]->subcategory_id === 14) {
                    
                $advert = DB::table("adverts as adv")->select(
                    "socials.insta_login",
                    "adv.region_id",
                    "adv.city_id",                                 
                    "adv.category_id",
                    "adv.subcategory_id",
                    "adv.startDate",
                    "adv.id", 
                    "adv.title", 
                    "adv.text", 
                    "adv.price", 
                    "adv.phone", 
                    "adv.coord_lat", 
                    "adv.coord_lon",
                    "realestate.property_type",                        
                    "realestate.rooms",
                    "realestate.area",               
                    "categories.name as category_name",
                    "categories.url as category_url",
                    "subcats.name as subcat_name",
                    "subcats.url as subcat_url",
                    "kz_region.url as region_url",
                    "kz_city.url as city_url",
                    DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
                    DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
                    DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"),
                    "adex_color.startDate as colorStartDate",		                            
                    "adex_color.finishDate as colorFinishDate",		                            
                    "adex_srochno.startDate as srochnoStartDate",		                            
                    "adex_srochno.finishDate as srochnoFinishDate",		                            
                    "adex_top.startDate as topStartDate",		                            
                    "adex_top.finishDate as topFinishDate",		                            			                            			                            
                    DB::raw("CASE WHEN realestate.ownership=0 THEN 'собственник' ELSE 'посредник' END as ownership"),
                    DB::raw("CASE WHEN realestate.kind_of_object=0 THEN 'вторичка' ELSE 'новостройка' END as kind_of_object"),
                    DB::raw("CASE 
                    WHEN realestate.type_of_building=0 THEN 'дом' 
                    WHEN realestate.type_of_building=1 THEN 'дача' 
                    WHEN realestate.type_of_building=2 THEN 'коттедж' 
                    ELSE '-' 
                    END as type_of_building"),                        
                    DB::raw("`kz_region`.`name` AS region_name, `kz_city`.`name` AS city_name"),
                    DB::raw("`kz_region`.`url` AS region_url, `kz_city`.`url` AS city_url"))
                    ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
                    ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			            
                    ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )
                    ->leftJoin("socials", "adv.city_id" , "=" , "socials.place_id" )		                            
                    ->join("categories", "adv.category_id" , "=" , "categories.id" )
                    ->join("subcats", "adv.subcategory_id" , "=" , "subcats.id" )
                    ->join("kz_region", "adv.region_id" , "=" , "kz_region.region_id" )                
                    ->join("kz_city", "adv.city_id" , "=" , "kz_city.city_id" )                                
                    ->join("sub_realestate as realestate", "adv.inner_id" , "=" , "realestate.id" )                                
                    ->where( "adv.id", $id )                                
                    ->whereRaw($periodSampleSql)                        
                    ->limit(1)
                    ->get();                                                        
            }
            // недвижимость за рубежом
            if ($advertData[0]->category_id === 2 && $advertData[0]->subcategory_id === 15) {
                    
                $advert = DB::table("adverts as adv")->select(
                    "socials.insta_login",
                    "adv.region_id",
                    "adv.city_id",                                 
                    "adv.category_id",
                    "adv.subcategory_id",
                    "adv.startDate",
                    "adv.id", 
                    "adv.title", 
                    "adv.text", 
                    "adv.price", 
                    "adv.phone", 
                    "adv.coord_lat", 
                    "adv.coord_lon",
                    "realestate.property_type",                        
                    "realestate.rooms",
                    "realestate.area",                        
                    "categories.name as category_name",
                    "categories.url as category_url", 
                    "subcats.name as subcat_name",
                    "subcats.url as subcat_url",
                    "kz_region.url as region_url",
                    "kz_city.url as city_url",
                    DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
                    DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
                    DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"),
                    "adex_color.startDate as colorStartDate",		                            
                    "adex_color.finishDate as colorFinishDate",		                            
                    "adex_srochno.startDate as srochnoStartDate",		                            
                    "adex_srochno.finishDate as srochnoFinishDate",		                            
                    "adex_top.startDate as topStartDate",		                            
                    "adex_top.finishDate as topFinishDate",		                            			                            
                    DB::raw("CASE WHEN realestate.ownership=0 THEN 'собственник' ELSE 'посредник' END as ownership"),
                    DB::raw("CASE WHEN realestate.kind_of_object=0 THEN 'вторичка' ELSE 'новостройка' END as kind_of_object"),
                    DB::raw("CASE 
                    WHEN realestate.type_of_building=0 THEN 'дом' 
                    WHEN realestate.type_of_building=1 THEN 'дача' 
                    WHEN realestate.type_of_building=2 THEN 'коттедж' 
                    ELSE '-' 
                    END as type_of_building"),                        
                    DB::raw("`kz_region`.`name` AS region_name, `kz_city`.`name` AS city_name"),
                    DB::raw("`kz_region`.`url` AS region_url, `kz_city`.`url` AS city_url"))
                    ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
                    ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			                
                    ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )
                    ->leftJoin("socials", "adv.city_id" , "=" , "socials.place_id" )		                        
                    ->join("categories", "adv.category_id" , "=" , "categories.id" )
                    ->join("subcats", "adv.subcategory_id" , "=" , "subcats.id" )
                    ->join("kz_region", "adv.region_id" , "=" , "kz_region.region_id" )                
                    ->join("kz_city", "adv.city_id" , "=" , "kz_city.city_id" )                                
                    ->join("sub_realestate as realestate", "adv.inner_id" , "=" , "realestate.id" )                                
                    ->where( "adv.id", $id )                                
                    ->whereRaw($periodSampleSql)
                    ->limit(1)
                    ->get();                                                        
            }
            
            // выборка для всего остального
            if ($advertData[0]->category_id > 2 && $advertData[0]->subcategory_id > 0) {                        
                
                \Debugbar::info("== остальное ==");
                \Debugbar::info("id: ".$id);

                    $advert = DB::table("adverts as adv")->select(
                        "socials.insta_login",
                        "adv.region_id",
                        "adv.city_id",                                 
                        "adv.startDate",
                        "adv.category_id",
                        "adv.subcategory_id",
                        "adv.id", 
                        "adv.title", 
                        "adv.text", 
                        "adv.price", 
                        "adv.phone", 
                        "adv.coord_lat", 
                        "adv.coord_lon", 
                        "categories.name as category_name",
                        "categories.url as category_url",
                        "subcats.name as subcat_name",
                        "subcats.url as subcat_url",                 
                        "kz_region.url as region_url",
                        "kz_city.url as city_url",
                        DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
                        DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
                        DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"),
                        "adex_color.startDate as colorStartDate",		                            
                        "adex_color.finishDate as colorFinishDate",		                            
                        "adex_srochno.startDate as srochnoStartDate",		                            
                        "adex_srochno.finishDate as srochnoFinishDate",		                            
                        "adex_top.startDate as topStartDate",		                            
                        "adex_top.finishDate as topFinishDate",		                            
                        DB::raw("kz_region.name AS region_name, kz_city.name AS city_name"),
                        DB::raw("`kz_region`.`url` AS region_url, `kz_city`.`url` AS city_url"))
                        ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
                        ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			                    
                        ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )
                        ->leftJoin("socials", "adv.city_id" , "=" , "socials.place_id" )
                        ->join("kz_region", "adv.region_id" , "=" , "kz_region.region_id" )                
                        ->join("kz_city", "adv.city_id" , "=" , "kz_city.city_id" )
                        ->join("categories", "adv.category_id" , "=" , "categories.id" )
                        ->join("subcats", "adv.subcategory_id" , "=" , "subcats.id" )
                        ->where( "adv.id", $id )
                        ->whereRaw($periodSampleSql)                        
                        ->limit(1)
                        ->get();                    
            }

            // категория другое
            if ($advertData[0]->category_id > 0 && !$advertData[0]->subcategory_id) {                        
                
                \Debugbar::info("== другое ==");

                $advert = DB::table("adverts as adv")->select(  
                    "socials.insta_login",                               
                    "adv.region_id",
                    "adv.city_id",
                    "adv.startDate",
                    "adv.category_id",                    
                    "adv.id", 
                    "adv.title", 
                    "adv.text", 
                    "adv.price", 
                    "adv.phone", 
                    "adv.coord_lat", 
                    "adv.coord_lon", 
                    "categories.name as category_name",
                    "categories.url as category_url",
                    "kz_region.url as region_url",
                    "kz_city.url as city_url",                    
                    DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
                    DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
                    DB::raw("(SELECT COUNT(*) FROM adex_top WHERE NOW() BETWEEN adex_top.startDate AND adex_top.finishDate AND adex_top.advert_id=adv.id) as top"),
                    "adex_color.startDate as colorStartDate",		                            
                    "adex_color.finishDate as colorFinishDate",		                            
                    "adex_srochno.startDate as srochnoStartDate",		                            
                    "adex_srochno.finishDate as srochnoFinishDate",		                            
                    "adex_top.startDate as topStartDate",		                            
                    "adex_top.finishDate as topFinishDate",
                    DB::raw("kz_region.name AS region_name, kz_city.name AS city_name"),
                    DB::raw("`kz_region`.`url` AS region_url, `kz_city`.`url` AS city_url"))
                    ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
                    ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			                    
                    ->leftJoin("adex_top", "adv.id", "=", "adex_top.advert_id" )
                    ->leftJoin("socials", "adv.city_id" , "=" , "socials.place_id" )	                    
                    ->join("kz_region", "adv.region_id" , "=" , "kz_region.region_id" )                
                    ->join("kz_city", "adv.city_id" , "=" , "kz_city.city_id" )
                    ->join("categories", "adv.category_id" , "=" , "categories.id" )                    
                    ->where( "adv.id", $id )
                    ->whereRaw($periodSampleSql)                        
                    ->limit(1)
                    ->get();                        
            }

            \Debugbar::info("-[выборка]-------------------");
            \DebugBar::info($advert); 
            \Debugbar::info("-----------------------------");                

            if ( !count($advert) ) {
                \Debugbar::info("0 - объявлений");                
                abort(404);
            }
            
            $images = DB::table("images")->selectRaw("concat(url,'/normal/', name) as imageName")->leftJoin("storages", "storages.id", "=", "images.storage_id")->where("images.advert_id", $id)->get();                
            
            \Debugbar::info("-[images]-------------------");
            \Debugbar::info($images);
            \Debugbar::info("---------------------");
            
            // ---------------------------------------------------------------
            // Похожие объявления
            // ---------------------------------------------------------------
            $similarAdverts = DB::table("adverts as adv")->select(
                "urls.url",
                "adv.id", 
                "adv.title", 
                "adv.price",
                "adv.startDate",            
                "adv.coord_lat",            
                "adv.coord_lon",            
                "kz_region.name as region_name",
                "kz_city.name as city_name",               
                DB::raw(Common::getPreviewImage("adv.id")))                
                ->leftJoin("urls", "adv.id", "=", "urls.advert_id" )
                ->join("kz_region", "adv.region_id", "=", "kz_region.region_id" )
                ->join("kz_city", "adv.city_id", "=", "kz_city.city_id" )                
                ->where("subcategory_id", $advert[0]->subcategory_id)
                ->where("adv.region_id", $advert[0]->region_id)
                ->where("adv.city_id", $advert[0]->city_id)
                ->whereRaw("NOW() BETWEEN adv.startDate AND adv.finishDate AND adv.public = true AND adv.id!=".$advert[0]->id)->limit(6)->get();                                                

                if (!count($similarAdverts)) {
                    \Debugbar::info("пустая выборка");                 
                }                        

            $petrovich = new Petrovich(Petrovich::GENDER_MALE);
                
            return view("details")
            ->with( "title", $advert[0]->title." в ".$petrovich->firstname($advert[0]->city_name, 4)." на сайте объявлений ".config('app.name') )
            ->with( "description", $advert[0]->title )
            ->with( "keywords", $advert[0]->title)                
            ->with( "advert", $advert[0])                            
            ->with( "images", $images)
            ->with( "vip_price", Common::getVipPrice())
            ->with( "srochno_torg_price", Common::getSrochnoTorgPrice())
            ->with( "color_price", Common::getColorPrice())
            ->with( "similarAdverts", $similarAdverts)
            ->with( "view", $request->view);
    }
}