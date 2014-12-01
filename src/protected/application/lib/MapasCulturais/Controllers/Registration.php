<?php
namespace MapasCulturais\Controllers;

use MapasCulturais\App;
use MapasCulturais\Traits;
use MapasCulturais\Definitions;
use MapasCulturais\Entities;

/**
 * Registration Controller
 *
 * By default this controller is registered with the id 'registration'.
 *
 *  @property-read \MapasCulturais\Entities\Registration $requestedEntity The Requested Entity
 */
class Registration extends EntityController {
    use Traits\ControllerUploads,
        Traits\ControllerAgentRelation;

    function __construct() {
        $app = App::i();
        $app->hook('POST(registration.upload):before', function() use($app) {
            $registration = $this->requestedEntity;
            foreach($registration->project->registrationFileConfigurations as $rfc){
                $fileGroup = new Definitions\FileGroup($rfc->fileGroupName, array('^application/.*'), 'The uploaded file is not a valid document.', true);
                $app->registerFileGroup('registration', $fileGroup);
            }
        });

        $app->hook('entity(Registration).file(rfc_<<*>>).insert:before', function() use ($app){
            // find registration file configuration
            $rfc = null;
            foreach($this->owner->project->registrationFileConfigurations as $r){
                if($r->fileGroupName === $this->group){
                    $rfc = $r;
                }
            }
            $finfo = pathinfo($this->name);

            $this->name = $this->owner->number . ' - ' . preg_replace ('/[^\. \-\_\p{L}\p{N}]/u', '', $rfc->title) . '.' . $finfo['extension'];
            $tmpFile = $this->tmpFile;
            $tmpFile['name'] = $this->name;
            $this->tmpFile = $tmpFile;
        });

        parent::__construct();
    }

    function getRequestedProject(){
        $app = App::i();
        if(!isset($this->urlData['projectId']) || !intval($this->urlData['projectId'])){
            $app->pass();
        }

        $project = $app->repo('Project')->find(intval($this->urlData['projectId']));

        if(!$project){
            $this->pass();
        }

        return $project;
    }

    function GET_create(){
        $this->requireAuthentication();

        $project = $this->getRequestedProject();

        $project->checkPermission('register');

        $registration = new $this->entityClassName;

        $registration->project = $project;

        $this->render('create', array('entity' => $registration));
    }

    function GET_view(){
        $entity = $this->requestedEntity;

        $entity->checkPermission('view');

        if($entity->status === Entities\Registration::STATUS_DRAFT){
            parent::GET_edit();
        }else{
            parent::GET_single();
        }
    }

    function GET_single(){
        App::i()->pass();
    }

    function GET_edit(){
        App::i()->pass();
    }

    function POST_setStatusTo(){
        $this->requireAuthentication();
        $app = App::i();

        $registration = $this->requestedEntity;

        if(!$registration){
            $app->pass();
        }

        $status = isset($this->postData['status']) ? $this->postData['status'] : null;

        $method_name = 'setStatusTo' . ucfirst($status);

        if(!method_exists($registration, $method_name)){
            if($app->request->isAjax()){
                $this->errorJson('Invalid status name');
            }else{
                $app->halt(200, 'Invalid status name');
            }
        }

        $registration->$method_name();

        if($app->request->isAjax()){
            $this->json($registration);
        }else{
            $app->redirect($app->request->getReferer());
        }
    }

    function POST_send(){
        $this->requireAuthentication();
        $app = App::i();

        $registration = $this->requestedEntity;

        if(!$registration){
            $app->pass();
        }

        if($errors = $registration->getSendValidationErrors()){
            $this->errorJson($errors);
        }else{
            $registration->send();
            if($app->request->isAjax()){
                $this->json($registration);
            }else{
                $app->redirect($app->request->getReferer());
            }
        }
    }
}