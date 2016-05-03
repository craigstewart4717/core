<?php

namespace App\Modules\Visittransfer\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Models\Mship\Account;
use App\Modules\Visittransfer\Http\Requests\AddRefereeApplicationRequest;
use App\Modules\Visittransfer\Http\Requests\SelectFacilityApplicationRequest;
use App\Modules\Visittransfer\Http\Requests\StartApplicationRequest;
use App\Modules\Visittransfer\Http\Requests\SubmitApplicationRequest;
use App\Modules\Visittransfer\Http\Requests\SubmitStatementApplicationRequest;
use App\Modules\Visittransfer\Models\Facility;
use App\Modules\Visittransfer\Models\Referee;
use Auth;
use Exception;
use Illuminate\Support\Facades\Gate;
use Input;
use Redirect;

class Application extends BaseController
{
    private $application = null;

    public function __construct(){
        $this->application = $this->getCurrentOpenApplicationForUser();
    }

    public function getStart($applicationType)
    {
        $this->authorize("create", new \App\Modules\Visittransfer\Models\Application());

        return $this->viewMake("visittransfer::application.terms")
                    ->with("applicationType", $applicationType)
                    ->with("application", new \App\Modules\Visittransfer\Models\Application);
    }

    public function postStart(StartApplicationRequest $request)
    {
        $application = Auth::user()->visitTransferApplications()->create([
            "type" => Input::get("application_type"),
        ]);

        return Redirect::route("visiting.application.facility");
    }
    
    public function getContinue(){
        if(Gate::allows("select-facility", Auth::user()->visitTransferCurrent())){
            return Redirect::route("visiting.application.facility");
        }

        if(Gate::allows("add-statement", Auth::user()->visitTransferCurrent())){
            return Redirect::route("visiting.application.statement");
        }

        if(Gate::allows("add-referee", Auth::user()->visitTransferCurrent())){
            return Redirect::route("visiting.application.referees");
        }

        if(Gate::allows("submit-application", Auth::user()->visitTransferCurrent())){
            return Redirect::route("visiting.application.submit");
        }

        return Redirect::route("visiting.dashboard");
    }

    public function getFacility()
    {
        $this->authorize("select-facility", $this->getCurrentOpenApplicationForUser());

        return $this->viewMake("visittransfer::application.facility")
                    ->with("application", $this->getCurrentOpenApplicationForUser())
                    ->with("facilities", $this->getCurrentOpenApplicationForUser()->potential_facilities);
    }

    public function postFacility(SelectFacilityApplicationRequest $request)
    {
        try {
            $this->application->setFacility(Facility::find(Input::get("facility_id")));
        } catch(Exception $e){
            return Redirect::route("visiting.application.facility")->withError($e->getMessage());
        }

        return Redirect::route("visiting.application.statement");
    }

    public function getStatement()
    {
        $this->authorize("add-statement", $this->application);

        $this->application->load("facility");

        return $this->viewMake("visittransfer::application.statement")
                    ->with("application", $this->application);
    }

    public function postStatement(SubmitStatementApplicationRequest $request){
        try {
            $this->application->setStatement(Input::get("statement"));
        } catch(Exception $e){
            return Redirect::route("visiting.application.statement")->withError($e->getMessage());
        }

        return Redirect::route("visiting.application.referees");
    }

    public function getReferees(){
        $this->authorize("add-referees", $this->application);

        $this->application->load("referees.account");

        return $this->viewMake("visittransfer::application.referees")
                    ->with("application", $this->application);
    }

    public function postReferees(AddRefereeApplicationRequest $request){
        try {
            $this->application->addReferee(
                Account::find(Input::get("referee_cid")),
                Input::get("referee_email"),
                Input::get("referee_relationship")
            );
        } catch(Exception $e){
            return Redirect::route("visiting.application.referees")->withError($e->getMessage());
        }

        $redirectRoute = "visiting.application.referees";

        if($this->application->number_references_required_relative == 0){
            $redirectRoute = "visiting.application.submit";
        }

        return Redirect::route($redirectRoute)->withSuccess("Referee added successfully! They will not be contacted until you submit your application.");
    }

    public function getSubmit(){
        $this->authorize("submit-application", $this->application);

        return $this->viewMake("visittransfer::application.submission")
                    ->with("application", $this->application);
    }

    public function postSubmit(SubmitApplicationRequest $request){
        try {
            $this->application->submit();
        } catch(Exception $e){
            return Redirect::route("visiting.application.submit")->withError($e->getMessage());
        }

        return Redirect::route("visiting.application.view", [$this->application->id])->withSuccess("Your application has been submitted! You will be notified when staff have reviewed the details.");
    }

    public function getView(\App\Modules\Visittransfer\Models\Application $application){
        $application->load("facility")->load("referees.account");

        return $this->viewMake("visittransfer::application.view")
                    ->with("application", $application);
    }

    private function getCurrentOpenApplicationForUser()
    {
        return Auth::user()->visitTransferCurrent();
    }
}
