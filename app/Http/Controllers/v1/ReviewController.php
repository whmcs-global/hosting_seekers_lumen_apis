<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\{AutoResponderTrait, SendResponseTrait, GetDataTrait};
use hisorange\BrowserDetect\Parser;
use App\Models\{User, Company_review, ReviewCriteria, Company_reply, Company_detail};

class ReviewController extends Controller
{
    use SendResponseTrait, AutoResponderTrait, GetDataTrait;

    /*
    Method Name:    getCountries
    Developer:      Shine Dezign
    Created Date:   2021-11/08 (yyyy-mm-dd)
    Purpose:        To get user Details
    Params:         [user_id,bool]
    */
    public function companyReview(Request $request){
		$validator = Validator::make($request->all(),[
            'review' => 'required|string'
        ]);
        if($validator->fails()){
            return $this->apiResponse('error', '422', $validator->errors()->all());
        }
    	try {
            $postData = $request->all();
            $reviewData = [];
            $companyId = jsdecode_userdata($postData['company_id']);
            $criterias = ReviewCriteria::get();
            foreach($criterias as $criteria){
                $criteriaKey = str_replace(' ','_',strtolower($criteria->name));
                $reviewData[$criteria->name] = $request->$criteriaKey;
            }
            $rawData = serialize($reviewData);
        	$data = array(
        		'rate_point' => $postData['overall_rating'],
        		'company_id' => $companyId,
                'review' => $postData['review'],
                'review_data' => $rawData,
                'status' => 1
            );
            $userip = 0 ;
            if(!$request->has('reviewId')){
                $userip = User::where(['id' => $companyId, 'ip_address' => $this->getClientIp()])->count();
                if($userip > 0)
                return $this->apiResponse('error', '400', config('constants.ERROR.IP_ISSUE'));
            }
            $companyDetail = Company_detail::where('user_id', $companyId)->first();
            if($request->has('reviewId')){
                //upload review image to server and save into data base
                $record = Company_review::where('id', jsdecode_userdata($postData['reviewId']))->first();
                $reviewUrl = 'https://www.hostingseekers.com/company/'.$companyDetail->slug.'/share/review/'.$record->uid;
                $imageName = $companyDetail->slug.'-review-'.$record->uid.'.png';
                Company_review::where('id', jsdecode_userdata($postData['reviewId']))->update($data);
                $reviewCount = Company_review::where('company_id', $companyId)->count();
                $reviewAvg = Company_review::where('company_id', $companyId)->avg('rate_point');
                Company_detail::where('user_id', $companyId)->update(['average_rating' => $reviewAvg, 'total_reviews' => $reviewCount]);
                $dataArray = ['url' => $reviewUrl, 'imageName' => $imageName];
                $response = hitCurl(config('constants.NODE_URL').'/generate/review', 'POST', $dataArray);
                $response = json_decode($response);
                if($response->success)
                Company_review::where('id', jsdecode_userdata($postData['reviewId']))->update(['review_image' => str_replace('http://localhost:4000/',  config('constants.nodeurl'), $response->path)]);
                return $this->apiResponse('success', '200', "Review ".config('constants.SUCCESS.UPDATE_DONE'));
            }
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.FORBIDDEN_ERROR'));
        }
    }

    /*
    Method Name:    getRating
    Developer:      Shine Dezign
    Created Date:   2021-11/08 (yyyy-mm-dd)
    Purpose:        To get user Details
    Params:         [user_id,bool]
    */
    
    public function getRating(Request $request, $id) {
        try{
            $rate = Company_review::select('id', 'company_id', 'user_id', 'uid', 'rate_point', 'review', 'review_data', 'created_at')->with(['company'])->where(['user_id' => $request->userid, 'id' => jsdecode_userdata($id)])
            ->first();
            $ratingArray = [];
            if($rate) {
                $raviewData = [
                    'id'=> jsencode_userdata($rate->id),
                    'company_id' => jsencode_userdata($rate->company_id),
                    'overall_rating' => $rate->rate_point,
                    'review' => $rate->review,
                    'overall_rating' => $rate->rate_point,
                ];
                foreach($rate->review_data as $key => $criteria){
                    $criteriaKey = str_replace(' ','_',strtolower($key));
                    $raviewData[$criteriaKey] = $criteria;
                }
                $ratingArray = $raviewData;
            }
            return $this->apiResponse('success', '200', 'Data fetched', $ratingArray);
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.FORBIDDEN_ERROR'));
        }
    }

    /*
    Method Name:    getRatings
    Developer:      Shine Dezign
    Created Date:   2021-11/08 (yyyy-mm-dd)
    Purpose:        To get user Details
    Params:         [user_id,bool]
    */
    
    public function getRatings(Request $request) {
        try {
            $ratings = Company_review::select('id', 'company_id', 'user_id', 'uid', 'rate_point', 'review', 'review_data', 'created_at')->with(['company', 'company_reply'])->where('user_id', $request->userid)
            ->orderByDesc('id')->paginate(config('constants.PAGINATION_NUMBER'));
            $ratingArray = [];
            $page = 1;
            if($request->has('page'))
            $page = $request->page;
            if($ratings->isNotEmpty()) {
                $rating = [
                    'count' => $ratings->count(),
                    'currentPage' => $ratings->currentPage(),
                    'hasMorePages' => $ratings->hasMorePages(),
                    'lastPage' => $ratings->lastPage(),
                    'nextPageUrl' => $ratings->nextPageUrl(),
                    'perPage' => $ratings->perPage(),
                    'previousPageUrl' => $ratings->previousPageUrl(),
                    'total' => $ratings->total()
                ];
                $raviewData = [];
                foreach($ratings as $rate){
                    $reply = null;
                    $replies = Company_reply::where(['company_review_id' => $rate->id, 'reply_by' => $rate->company_id])
                    ->orderByDesc('id')->first();
                    if(!is_null($replies)){
                        $reply = $replies->reply;
                    }
                    array_push($raviewData, ['id'=> jsencode_userdata($rate->id), 'company_id' => jsencode_userdata($rate->company_id), 'company_name' => strlen($rate->company->company_detail->company_name) > 20 ? substr($rate->company->company_detail->company_name, 0, 20).'...' : $rate->company->company_detail->company_name, 'overall_rating' => $rate->rate_point, 'review' => $rate->review, 'reply' => $reply, 'created_at' => change_date_format($rate->created_at)]);
                }
                $rating['data'] = $raviewData;
                $ratingArray = $rating;
            }
            return $this->apiResponse('success', '200', 'Data fetched', $ratingArray);
            
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.FORBIDDEN_ERROR'));
        }
    }

    /*
    Method Name:    getReviewCriteria
    Developer:      Shine Dezign
    Created Date:   2021-11/08 (yyyy-mm-dd)
    Purpose:        To get user Details
    Params:         [user_id,bool]
    */
    
    public function getReviewCriteria(Request $request) {
        try{
            $ratings = ReviewCriteria::select('id', 'name')->get();
            $dataArray = $ratings;
            return $this->apiResponse('success', '200', 'Data fetched', $dataArray);
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.FORBIDDEN_ERROR'));
        }
    }
}
