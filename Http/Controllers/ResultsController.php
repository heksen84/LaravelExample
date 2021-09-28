<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Helpers\Common;
use App\Helpers\Petrovich;
use App\Categories;
use App\SubCats;
use App\Regions;
use App\Places;

class ResultsController extends Controller {

    const FULL_SITE_DESC = " на сайте объявлений";
    
    // --- Замена тегов в строке и склонение их по указанному роду
    private function ReplaceTagsAndSklonCategoryItem($title, $locationName, $category_name, $gender, $optype_on) {

        $petrovich = new Petrovich($gender);                    
        $category_name = mb_strtolower($petrovich->firstname($category_name, 0 ));
        
        return str_replace("@category", $category_name, str_replace("@place", $locationName, $title ));
    }
        
    // получить данные категории    
    private function getCategoryData(Request $request, $category) {          
        
        $table = new Categories();
        $data = $table::select("id", "name", "title", "description", "keywords", "h1")->where("url", $category)->get();

        if (!count($data))
            abort(404);             

        return $data;
    }
    
    // получить данные подкатегории
    private function getSubCategoryData(Request $request, $subcategory) {          
        
        $table = new SubCats();
        $data = $table::select("id", "name", "title", "description", "keywords", "h1")->where("url", $subcategory)->get();

        if (!count($data))
            abort(404);          

        return $data;
    }
    
    // получить данные региона
    private function getRegionData($region) {                        
        
        $regionId = Regions::select("region_id", "name")->where("url", $region)->get();        
        \Debugbar::info("ID региона: ".$regionId[0]->region_id);

        // FIXME: NEED?
        if (!count($regionId)) {
            abort(404);             
        }        
        return $regionId[0];
    }
    
    // получить данные города / села
    private function getPlaceData($region, $place) {                
        
        $placeId = Places::select("city_id", "name")->where("region_id", $region)->where("url", $place)->get();        
        \Debugbar::info("ID города/села: ".$placeId[0]->city_id);

        // FIXME: NEED?
        if (!count($placeId)) {
            abort(404);             
        }

        return $placeId[0];
    }
    
    // получить расположение
    private function getLocationName($val, $isRegion) {                        

        if ( $val === null ) 
            return "Казахстане";
	    else 
            if ($isRegion) {
                $petrovich = new Petrovich(Petrovich::GENDER_FEMALE);											
                return $petrovich->firstname($val, 4). " области";
            }
            else {
                $petrovich = new Petrovich(Petrovich::GENDER_MALE);											
                return $petrovich->firstname($val, 4);
            }	    
    }    
    
    // результаты - общий запрос
    public function getCategoryResults(Request $request, $region, $place, $category) {  
        
        \Debugbar::info("getCategoryResults");        
        \Debugbar::info("start_price: ".$request->start_price);
        \Debugbar::info("end_price: ".$request->end_price);        
                        
        $startPrice = $request->start_price;
        $endPrice = $request->end_price;        
        $priceBetweenSql = "";        
        $regionData = null;
        $placeData  = null;

        if ($startPrice && $endPrice) 
            $priceBetweenSql = " AND price BETWEEN ".$startPrice." AND ".$endPrice;

        $categories = $this->getCategoryData($request, $category);
                
        \Debugbar::info("optype: ".$request->optype);

        // ----------- optype -----------
        // проверка на тип операции
        (!$request->optype)?$optypeSql = "":is_numeric($request->optype)?$optypeSql = " AND optype=".$request->optype:$optypeSql = "";        
        
        if (is_numeric($request->optype)) {
            switch($request->optype) {
                case 0: $optypeTitle = "Покупка"; break;
                case 1: $optypeTitle = "Продажа"; break;
                case 2: $optypeTitle = "Обмен"; break;
                case 3: $optypeTitle = "Услуги"; break;
                case 4: $optypeTitle = "Отдача даром"; break;
                default: $optypeTitle = "";
            }
        } else 
            $optypeTitle = "";
                
                    
        if (!$region && !$place)
            $whereRaw = "category_id = ".$categories[0]->id." AND NOW() BETWEEN adv.startDate AND adv.finishDate AND adv.public = true".$optypeSql;

        if ($region && !$place) {
            $regionData = $this->getRegionData($region);             
           \Debugbar::info($regionData->region_id);
            $whereRaw = "adv.region_id = ".$regionData->region_id." AND adv.category_id = ".$categories[0]->id." AND NOW() BETWEEN adv.startDate AND adv.finishDate AND adv.public = true".$optypeSql;
        }

        if ($region && $place) {            
            $regionData = $this->getRegionData($region); 
            $placeData = $this->getPlaceData($regionData->region_id, $place);
            $whereRaw = "adv.region_id = ".$regionData->region_id." AND adv.city_id = ".$placeData->city_id." AND adv.category_id = ".$categories[0]->id." AND NOW() BETWEEN adv.startDate AND adv.finishDate AND adv.public = true".$optypeSql;
        }
                                                
        $items = DB::table("adverts as adv")->select(
            "urls.url",
            "adv.id", 
            "adv.title", 
            "adv.price",
            "adv.startDate",            
            "kz_region.name as region_name",
            "kz_city.name as city_name",
            DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
            DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
            DB::raw(Common::getPreviewImage("adv.id")))
            ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
            ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			                    
            ->join("urls", "adv.id", "=", "urls.advert_id" )
            ->join("kz_region", "adv.region_id", "=", "kz_region.region_id" )
            ->join("kz_city", "adv.city_id", "=", "kz_city.city_id" )                
            ->whereRaw($whereRaw)
            ->orderBy("adv.startDate", "DESC")
            ->paginate(10)
            ->onEachSide(1);

        \Debugbar::info($items);

        if ($regionData) {                        
            $locationName = $this->getLocationName($regionData->name, true);
        }        
        if ($placeData) {
            $locationName = $this->getLocationName($placeData->name, false);
        }
        if (!$regionData && !$placeData) {
            $locationName = $this->getLocationName(null, null);
        }

        $filters = array (
		    "price_ot" => $request->price_ot,
		    "price_do" => $request->price_do,		    
        );                        
                                                
        return view("results")    
        ->with("title", $optypeTitle." ".$this->ReplaceTagsAndSklonCategoryItem($categories[0]->title, $locationName, $categories[0]->name, 1, 0).self::FULL_SITE_DESC)         
        ->with("description", $this->ReplaceTagsAndSklonCategoryItem($categories[0]->description, $locationName, $categories[0]->name, 1, 0))         
        ->with("keywords", $this->ReplaceTagsAndSklonCategoryItem($categories[0]->keywords, $locationName, $categories[0]->name, 1, 0))         
        ->with("h1", $optypeTitle." ".$this->ReplaceTagsAndSklonCategoryItem($categories[0]->h1, $locationName, $categories[0]->name, 1, 0))
        ->with("items", $items)
        ->with("categoryId", $categories[0]->id)
        ->with("subcategoryId", null)         
        ->with("region", null)
        ->with("city", null)
        ->with("category", $category)
        ->with("subcategory", null)         
        ->with("page", $request->page?$request->page:0)
        ->with("start_price", $request->start_price)
        ->with("end_price", $request->end_price)
        ->with("filters", $filters)
        ->with("moderation", Cache::get("moderation"));
    }

    public function getCountryCategoryResults(Request $request, $category) {
        return $this->getCategoryResults($request, null, null, $category);
    }

    public function getRegionCategoryResults(Request $request, $region, $category) {
        return $this->getCategoryResults($request, $region, null, $category);
    }

    public function getCityCategoryResults(Request $request, $region, $place, $category) {
        return $this->getCategoryResults($request, $region, $place, $category);
    }

    // -------------------------------------------------------------
    // результаты по стране
    // -------------------------------------------------------------    
    public function getCountrySubCategoryResults(Request $request, $category, $subcategory) {

        $categories = $this->getCategoryData($request, $category);                         
        $subcategories = $this->getSubCategoryData($request, $subcategory);

        $priceBetweenSql = "";

        if ($request->price_ot && $request->price_do) 
            $priceBetweenSql = " AND price BETWEEN ".$request->price_ot." AND ".$request->price_do;

            // проверка на тип операции
            (!$request->optype)?$optypeSql = "":is_numeric($request->optype)?$optypeSql = " AND optype=".$request->optype:$optypeSql = "";
                                           
        // ------------------------------------------------------------------
        // легковой автомобиль
        // ------------------------------------------------------------------
        if ($category === "transport" && $subcategory === "legkovoy-avtomobil") {

            \Debugbar::info("Легковой автомобиль");

	        // если у нас авто, то мы должны применить фильры от авто и вернуть входящие параметры во вьюху            
            $filters = array (
		    "price_ot" => $request->price_ot,
		    "price_do" => $request->price_do,
		    "mark" => $request->mark, 
		    "model" => $request->model,
            "year_ot" => $request->year_ot,
            "year_do" => $request->year_do,
            "mileage_ot" => $request->mileage_ot,
            "mileage_do" => $request->mileage_do
            );
            
        }
        else
        // ------------------------------------------------------------------
        // легковой автомобиль
        // ------------------------------------------------------------------
        if ($category === "transport" && $subcategory === "gruzovoy-avtomobil") {

            \Debugbar::info("Грузовой автомобиль");

	        // если у нас авто, то мы должны применить фильры от авто и вернуть входящие параметры во вьюху            
            $filters = array (
                "price_ot" => $request->price_ot,
                "price_do" => $request->price_do,		    
            );            
        }
	    else
            $filters = array ("price_ot" => $request->price_ot, "price_do" => $request->price_do); 
                            
            $items = DB::table("adverts as adv")->select(
            "urls.url",
            "adv.id", 
            "adv.title", 
            "adv.price", 
            "adv.startDate",            
            "kz_region.name as region_name",
            "kz_city.name as city_name",
            DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
            DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
            DB::raw(Common::getPreviewImage("adv.id")))
            ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
            ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			                    
            ->join("urls", "adv.id", "=", "urls.advert_id" )
            ->join("kz_region", "adv.region_id", "=", "kz_region.region_id" )
            ->join("kz_city", "adv.city_id", "=", "kz_city.city_id" )                
            ->where("subcategory_id", $subcategories[0]->id.$priceBetweenSql)
            ->whereRaw("NOW() BETWEEN adv.startDate AND adv.finishDate AND adv.public = true".$optypeSql)
            ->orderBy("adv.startDate", "DESC")
            ->paginate(10)
            ->onEachSide(1);        

            \Debugbar::info("субкатегория: ".$subcategory);       
            \Debugbar::info("id субкатегории: ".$subcategories);      
            \Debugbar::info($items);

            $locationName = $this->getLocationName(null, null);
            
            // Добавить функцию ReplaceTagsAndSklonCategoryItem($subcategories[0]->title)
                
            return view("results")    
            ->with("title", str_replace("@place", $locationName, $subcategories[0]->title ).self::FULL_SITE_DESC)         
            ->with("description", str_replace("@place", $locationName, $subcategories[0]->description ))         
            ->with("keywords", str_replace("@place", $locationName, $subcategories[0]->keywords ))
            ->with("h1", str_replace("@place", $locationName, $subcategories[0]->h1 ))         
            ->with("items", $items)
            ->with("categoryId", $categories[0]->id)
            ->with("subcategoryId", $subcategories[0]->id)         
            ->with("region", null)
            ->with("city", null)
            ->with("category", $category)
            ->with("subcategory", $subcategory)         
            ->with("page", $request->page?$request->page:0)
            ->with("filters", $filters)
            ->with("moderation", Cache::get("moderation"));
    }

    // -------------------------------------------------------------
    // результаты по области
    // -------------------------------------------------------------
    public function getRegionSubCategoryResults(Request $request, $region, $category, $subcategory) {
        
        // если у нас авто, то мы должны применить фильры от авто и вернуть входящие параметры во вьюху            
        $filters = array (
		    "price_ot" => $request->price_ot,
		    "price_do" => $request->price_do,		    
        );

         \Debugbar::info("start_price: ".$request->start_price);
         \Debugbar::info("end_price: ".$request->end_price);        
                         
         $startPrice = $request->start_price;
         $endPrice = $request->end_price;
 
         $priceBetweenSql = "";         
 
         if ($startPrice && $endPrice) 
             $priceBetweenSql = " AND price BETWEEN ".$startPrice." AND ".$endPrice;

            // проверка на тип операции
            (!$request->optype)?$optypeSql = "":is_numeric($request->optype)?$optypeSql = " AND optype=".$request->optype:$optypeSql = "";
                        
            $categories = $this->getCategoryData($request, $category);
            $subcategories = $this->getSubCategoryData($request, $subcategory);
            $regionData = $this->getRegionData($region);           
         
         $items = DB::table("adverts as adv")->select(
            "urls.url",
            "adv.id", 
            "adv.title", 
            "adv.price", 
            "adv.startDate",            
            "kz_region.name as region_name",
            "kz_city.name as city_name",
            DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
            DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
            DB::raw(Common::getPreviewImage("adv.id")))
            ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
            ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			                    
            ->join("urls", "adv.id", "=", "urls.advert_id" )
            ->join("kz_region", "adv.region_id", "=", "kz_region.region_id" )
            ->join("kz_city", "adv.city_id", "=", "kz_city.city_id" )                
            ->where("subcategory_id", $subcategories[0]->id.$priceBetweenSql)
            ->where("adv.region_id", $regionData->region_id)
            ->whereRaw("NOW() BETWEEN adv.startDate AND adv.finishDate AND adv.public = true".$optypeSql)
            ->orderBy("adv.startDate", "DESC")
            ->paginate(10)
            ->onEachSide(1);                 
 
         \Debugbar::info("субкатегория: ".$subcategory);       
         \Debugbar::info("id субкатегории: ".$subcategories);      
         \Debugbar::info($items);
 
         $locationName = $this->getLocationName($regionData->name, true);
                 
         return view("results")    
         ->with("title", str_replace("@place", $locationName, $subcategories[0]->title).self::FULL_SITE_DESC)
         ->with("description", str_replace("@place", $locationName, $subcategories[0]->description))         
         ->with("keywords", str_replace("@place", $locationName, $subcategories[0]->keywords))
         ->with("h1", str_replace("@place", $locationName, $subcategories[0]->h1 ))         
         ->with("items", $items)
         ->with("categoryId", $categories[0]->id)
         ->with("subcategoryId", $subcategories[0]->id)          
         ->with("region", $region)
         ->with("city", null)
         ->with("category", $category)
         ->with("subcategory", $subcategory)          
         ->with("page", $request->page?$request->page:0)
         ->with("start_price", $request->start_price)
         ->with("end_price", $request->end_price)
         ->with("filters", $filters)
         ->with("moderation", Cache::get("moderation"));
    }

    // -------------------------------------------------------------
    // результаты по городу либо селу
    // -------------------------------------------------------------
    public function getCitySubCategoryResults(Request $request, $region, $place, $category, $subcategory) {   
        
        // если у нас авто, то мы должны применить фильры от авто и вернуть входящие параметры во вьюху            
        $filters = array (
		    "price_ot" => $request->price_ot,
		    "price_do" => $request->price_do,		    
        );

        \Debugbar::info("--- getCitySubCategoryResults ---"); 
        \Debugbar::info("start_price: ".$request->start_price);
        \Debugbar::info("end_price: ".$request->end_price);        
                         
         $startPrice = $request->start_price;
         $endPrice = $request->end_price;
 
         $priceBetweenSql="";
 
         if ($startPrice && $endPrice) 
             $priceBetweenSql = " AND price BETWEEN ".$startPrice." AND ".$endPrice;

            // проверка на тип операции
            (!$request->optype)?$optypeSql = "":is_numeric($request->optype)?$optypeSql = " AND optype=".$request->optype:$optypeSql = "";
                        
            $categories = $this->getCategoryData($request, $category);
            $subcategories = $this->getSubCategoryData($request, $subcategory);
            $regionData = $this->getRegionData($region); 
            $placeData = $this->getPlaceData($regionData->region_id, $place);

            \Debugbar::info("------------------");
            \Debugbar::info($placeData);
            \Debugbar::info("------------------");
 
         $items = DB::table("adverts as adv")->select(
            "urls.url",
            "adv.id", 
            "adv.title", 
            "adv.price",
            "adv.startDate",            
            "kz_region.name as region_name",
            "kz_city.name as city_name",
            DB::raw("(SELECT COUNT(*) FROM adex_color WHERE NOW() BETWEEN adex_color.startDate AND adex_color.finishDate AND adex_color.advert_id=adv.id) as color"),                        
            DB::raw("(SELECT COUNT(*) FROM adex_srochno WHERE NOW() BETWEEN adex_srochno.startDate AND adex_srochno.finishDate AND adex_srochno.advert_id=adv.id) as srochno"),
            DB::raw(Common::getPreviewImage("adv.id")))
            ->leftJoin("adex_color", "adv.id", "=", "adex_color.advert_id" )
            ->leftJoin("adex_srochno", "adv.id", "=", "adex_srochno.advert_id" )			                    
            ->join("urls", "adv.id", "=", "urls.advert_id" )
            ->join("kz_region", "adv.region_id", "=", "kz_region.region_id" )
            ->join("kz_city", "adv.city_id", "=", "kz_city.city_id" )                
            ->where("subcategory_id", $subcategories[0]->id.$priceBetweenSql)
            ->where("adv.region_id", $regionData->region_id)
            ->where("adv.city_id", $placeData->city_id)
            ->whereRaw("NOW() BETWEEN adv.startDate AND adv.finishDate AND adv.public = true".$optypeSql)
            ->orderBy("adv.startDate", "DESC")
            ->paginate(10)
            ->onEachSide(1);
  
         \Debugbar::info("субкатегория: ".$subcategory);       
         \Debugbar::info("id субкатегории: ".$subcategories);      
         \Debugbar::info($items);

         \DebugBar::info("tuta");
 
         $locationName = $this->getLocationName($placeData->name, false);         
                 
         return view("results")    
         ->with("title", str_replace("@place", $locationName, $subcategories[0]->title ).self::FULL_SITE_DESC)         
         ->with("description", str_replace("@place", $locationName, $subcategories[0]->description ))         
         ->with("keywords", str_replace("@place", $locationName, $subcategories[0]->keywords ))
         ->with("h1", str_replace("@place", $locationName, $subcategories[0]->h1 ))         
         ->with("items", $items)
         ->with("categoryId", $categories[0]->id)
         ->with("subcategoryId", $subcategories[0]->id)     
         ->with("region", $region)
         ->with("city", $place)
         ->with("category", $category)
         ->with("subcategory", $subcategory)          
         ->with("page", $request->page?$request->page:0)
         ->with("start_price", $request->start_price)
         ->with("end_price", $request->end_price)
         ->with("filters", $filters)
         ->with("moderation", Cache::get("moderation"));
    }    
}
