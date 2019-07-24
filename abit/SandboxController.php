<?php

namespace common\modules\abiturient\controllers;

use common\models\dictionary\EgeDiscipline;
use common\models\dictionary\Fias;
use common\models\dictionary\Speciality;
use common\models\User;
use common\modules\abiturient\models\AbiturientQuestionary;
use common\modules\abiturient\models\AddressData;
use common\modules\abiturient\models\bachelor\ApplicationHistory;
use common\modules\abiturient\models\bachelor\ApplicationType;
use common\modules\abiturient\models\bachelor\BachelorApplication;
use common\modules\abiturient\models\bachelor\BachelorSpeciality;
use common\modules\abiturient\models\bachelor\EducationData;
use common\modules\abiturient\models\bachelor\EgeResult;
use common\modules\abiturient\models\bachelor\EgeYear;
use common\modules\abiturient\models\IndividualAchievement;
use common\modules\abiturient\models\PassportData;
use common\modules\abiturient\models\PersonalData;
use common\modules\abiturient\models\PrintForm;
use common\modules\abiturient\models\bachelor\ApplicationSearch;
use common\models\Attachment;
use yii\db\Query;
use Yii;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\Controller;

/**
 * Site controller
 */
class SandboxController extends Controller
{
    public function getViewPath()
    {
        return \Yii::getAlias('@common/modules/abiturient/views/sandbox');
    }

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'controllers' => ['sandbox'],
                        'allow' => false,
                        'roles' => ['administrator'],
                    ],
                    [
                        'actions' => ['index', 'moderate', 'view', 'decline',
                            'removespeciality', 'removeresult',
                            'addspecialities', 'updateresult', 'addresult',
                            'unblock', 'approved', 'declined', 'dormitory', 'all', 'update-questionary',
                            'update-examresult', 'update-ege', 'questionaries', 'view-questionary', 'get-all-attachments', 'bind'],
                        'allow' => true,
                        'roles' => ['manager']
                    ],
                ],
            ],
        ];
    }

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction'
            ]
        ];
    }

    public function actionUpdateQuestionary($id, $questionary_id)
    {
        $questionary = AbiturientQuestionary::findOne((int)$questionary_id);
        if ($questionary != null && $questionary->user->guid != null) {
            $questionary->getFrom1C();
        }

        return $this->redirect(Url::to(['sandbox/moderate', 'id' => $id]), 302);
    }

    public function actionUpdateExamresult($id)
    {
        $application = BachelorApplication::findOne((int)$id);
        if ($application != null && $application->user->id != null) {
            $application->getExamResultsFrom1C();
        }
        return $this->redirect(Url::to(['sandbox/moderate', 'id' => $id]), 302);
    }

    public function actionUpdateEge($id)
    {
        $application = BachelorApplication::findOne((int)$id);
        if ($application != null && $application->user->id != null) {
            $application->getEgeFrom1C();
        }
        return $this->redirect(Url::to(['sandbox/moderate', 'id' => $id]), 302);
    }

    public function actionIndex($old = null, $block = null)
    {
        $user = \Yii::$app->user->identity;

        $searchModel = new ApplicationSearch();

        $listOfAdmissionCampaign = $this->getListOfAdmissionCampaign($user->id);

        $applicationsDataProvider = $searchModel->search(Yii::$app->request->queryParams, 'moderate', ArrayHelper::getColumn($listOfAdmissionCampaign, 'code'));

        if ($old == "1") {
            Yii::$app->session->setFlash('alert', [
                'body' => 'Открытое вами заявление устарело и было актуализированно из ПК',
                'options' => ['class' => 'alert-info']
            ]);
        }
        if ($block == "1") {
            Yii::$app->session->setFlash('alert', [
                'body' => 'Заявление, которое вы пытались открыть, уже проверяется другим модератором',
                'options' => ['class' => 'alert-danger']
            ]);
        }

        return $this->render("index", [
            'applications' => $applicationsDataProvider,
            'searchModel' => $searchModel,
            'type' => 'moderate',
            'listOfAdmissionCampaign' => $listOfAdmissionCampaign,
        ]);
    }

    public function actionAll()
    {
        $user = \Yii::$app->user->identity;

        $searchModel = new ApplicationSearch();

        $listOfAdmissionCampaign = $this->getListOfAdmissionCampaign($user->id);

        $applicationsDataProvider = $searchModel->search(Yii::$app->request->queryParams, 'all', ArrayHelper::getColumn($listOfAdmissionCampaign, 'code'));

        return $this->render("index", [
            'applications' => $applicationsDataProvider,
            'searchModel' => $searchModel,
            'type' => 'all',
            'listOfAdmissionCampaign' => $listOfAdmissionCampaign,
        ]);
    }

    public function actionApproved()
    {
        $user = \Yii::$app->user->identity;

        $searchModel = new ApplicationSearch();

        $listOfAdmissionCampaign = $this->getListOfAdmissionCampaign($user->id);

        $applicationsDataProvider = $searchModel->search(Yii::$app->request->queryParams, 'approved', ArrayHelper::getColumn($listOfAdmissionCampaign, 'code'));

        return $this->render("index", [
            'applications' => $applicationsDataProvider,
            'searchModel' => $searchModel,
            'type' => 'approved',
            'listOfAdmissionCampaign' => $listOfAdmissionCampaign,
        ]);
    }

    public function actionDeclined()
    {
        $user = \Yii::$app->user->identity;

        $searchModel = new ApplicationSearch();

        $listOfAdmissionCampaign = $this->getListOfAdmissionCampaign($user->id);

        $applicationsDataProvider = $searchModel->search(Yii::$app->request->queryParams, 'declined', ArrayHelper::getColumn($listOfAdmissionCampaign, 'code'));

        return $this->render("index", [
            'applications' => $applicationsDataProvider,
            'searchModel' => $searchModel,
            'type' => 'declined',
            'listOfAdmissionCampaign' => $listOfAdmissionCampaign,
        ]);
    }

    public function actionQuestionaries()
    {
        $searchModel = new \common\modules\abiturient\models\QuestionarySearch();
        $questionariesDataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render("questionaries", [
            'questionaries' => $questionariesDataProvider,
            'searchModel' => $searchModel,
            'type' => 'questionaries'
        ]);
    }

    public function actionModerate($id)
    {
        $request = Yii::$app->request;
        $errors = [];
        $error_text = '';
        $application = BachelorApplication::findOne((int)$id);
        if ($application->status != BachelorApplication::STATUS_SENDED && $application->status != BachelorApplication::STATUS_REJECTED_BY1C) {
            return $this->redirect('/sandbox/index', 302);
        }
        if ($application->block_status == BachelorApplication::BLOCK_STATUS_ENABLED && $application->blocker_id != null && $application->blocker_id != \Yii::$app->user->identity->id) {
            return $this->redirect(Url::toRoute(['/sandbox/index', 'block' => 1]), 302);
        }
        if ($request->isPost && $application->block_status != BachelorApplication::BLOCK_STATUS_ENABLED) {
            Yii::$app->session->set('unblocked' . $application->id, '1');
            return $this->redirect(Url::to(['sandbox/moderate', 'id' => $id]), 302);
        }
        if (!$application->canModerate()) {
            return $this->redirect(Url::toRoute(['/sandbox/index', 'old' => 1]), 302);
        }
        $unblocked = Yii::$app->session->get('unblocked' . $application->id);
        $error = null;

        if (!$request->isPost && $unblocked != '1') {
            $application->block_status = BachelorApplication::BLOCK_STATUS_ENABLED;
            $application->blocker_id = \Yii::$app->user->identity->id;
            $application->save();
        }

        if (isset($unblocked) && $unblocked == "1") {
            $error = "1";
            Yii::$app->session->set('unblocked' . $application->id, '0');
        }

        $add_errors_json = Yii::$app->session->get('add_errors');
        $add_errors = json_decode($add_errors_json);
        Yii::$app->session->remove('add_errors');

        $questionary = AbiturientQuestionary::findOne(['user_id' => $application->user_id]);
        $hasErrors = false;
        if ($request->isPost && sizeof($application->specialities) > 0) {
            $application->load(Yii::$app->request->post());

            $user = User::findOne($application->user_id);
            if (Yii::$app->request->post('user_email') != null && strlen(Yii::$app->request->post('user_email')) > 0 && $user->email != Yii::$app->request->post('user_email')) {
                $validator = new \yii\validators\EmailValidator();
                $error = '';
                if ($validator->validate(Yii::$app->request->post('user_email'), $error)) {
                    $user->email = Yii::$app->request->post('user_email');
                    $user->save();
                }
            }

            $personal_data = PersonalData::findOne(['questionary_id' => $questionary->id]);
            $personal_data->load(Yii::$app->request->post());
            $passport_data = PassportData::findOne(['questionary_id' => $questionary->id]);
            $passport_data->load(Yii::$app->request->post());
            $address_data = AddressData::findOne(['questionary_id' => $questionary->id]);
            $address_data->load(Yii::$app->request->post());
            if ($address_data->street_id != null) {
                $street = Fias::findOne(['id' => $address_data->street_id, 'archive' => 0]);
                if ($street != null) {
                    $address_data->kladr_code = $street->code;
                }
            }

            $address_data->cleanUnusedAttributes();

            if ($address_data->area_id == "null") {
                $address_data->area_id = null;
            }
            if ($address_data->country != null && $address_data->country->code != Yii::$app->configurationManager->getCode('russia_code')) {
                $address_data->not_found = 1;
            }

            $education_data = EducationData::findOne(['application_id' => $application->id]);
            $education_data->load(Yii::$app->request->post());
            $edu_data = Yii::$app->request->post("EducationData");
            if (!isset($edu_data['document_type_id'])) {
                $education_data->document_type_id = null;
            }
            if ($application->validate() && $personal_data->validate()
                && $passport_data->validate()
                && $address_data->validate()
                && $education_data->validate()
            ) {
                $application->save();
                $personal_data->save();
                $passport_data->save();
                $address_data->save();
                $education_data->save();
                $questionary = AbiturientQuestionary::findOne(['user_id' => $application->user_id]);
                $application = BachelorApplication::findOne((int)$id);

                $q_result = $questionary->pushTo1C();

                $ia_result = true;
                $ege_result = true;

                $app_result = true;

                if ($q_result) {
                    $ia_result = false;
                    $ege_result = false;
                    $register_result = false;
                    $app_result = false;

                    $user = User::findOne($application->user_id);
                    $ia_result = $user->pushIaTo1C();

                    if ($q_result) {
                        $questionary->status = AbiturientQuestionary::STATUS_APPROVED;
                        $questionary->approver_id = Yii::$app->user->identity->id;
                        $questionary->approved_at = time();
                        $questionary->save();
                    } else {
                        $questionary->status = AbiturientQuestionary::STATUS_REJECTED_BY1C;
                        $questionary->save();
                    }
                    $ege_result = $application->pushEgeTo1C();

                    $app_result = $application->pushApplicationTo1C();


                }

                if ($q_result === true && $ege_result === true && $app_result === true) {
                    $application->status = BachelorApplication::STATUS_APPROVED;
                    $application->block_status = BachelorApplication::BLOCK_STATUS_DISABLED;
                    $application->approver_id = Yii::$app->user->identity->id;
                    $application->approved_at = time();
                    $application->save();
                    $application->addModerateHistory();
                    ApplicationHistory::deleteAll(['application_id' => $application->id]);
                    $questionary->getFrom1C();
                    Yii::$app->notifier->notifyAboutApplyApplication($application->user_id);
                    return $this->redirect('/sandbox/index', 302);
                } else {
                    $application->status = BachelorApplication::STATUS_REJECTED_BY1C;
                    $application->save();
                    $hasErrors = true;
                    if (is_string($q_result)) {
                        $error_text .= Html::tag('strong', $this->formatError($q_result));
                    }
                    if (is_string($ege_result)) {
                        $error_text .= Html::tag('strong', $this->formatError($ege_result));
                    }
                    if (is_string($app_result)) {
                        $error_text .= Html::tag('strong', $this->formatError($app_result));
                    }

                }
            }
        }

        if (sizeof($application->errors) > 0) {
            $errors[] = $application->errors;
        }
        if (isset($personal_data) && sizeof($personal_data->errors) > 0) {
            $errors[] = $personal_data->errors;
        }
        if (isset($passport_data) && sizeof($passport_data->errors) > 0) {
            $errors[] = $passport_data->errors;
        }
        if (isset($address_data) && sizeof($address_data->errors) > 0) {
            $errors[] = $address_data->errors;
        }
        if (isset($education_data) && sizeof($education_data->errors) > 0) {
            $errors[] = $education_data->errors;
        }

        $attached_Ids = array_map(create_function('$o', 'return $o->speciality_id;'), $application->specialities);
        $not_allowed_Ids = [];
        //not allow via step specs
        foreach ($application->type->campaign->info as $info) {
            if (time() >= $info->date_final) {
                $specs = Speciality::find()
                    ->active()
                    ->andWhere(['not in', 'id', $attached_Ids])
                    ->andWhere(['campaign_code' => $application->type->campaign->code])
                    ->andWhere(['finance_code' => $info->finance_code])
                    ->andWhere(['eduform_code' => $info->eduform_code])
                    ->andWhere(['detail_group_code' => $info->detail_group_code])
                    ->andWhere(['receipt_allowed' => 1])
                    ->all();
                $id_array = array_map(create_function('$o', 'return $o->id;'), $specs);
                $not_allowed_Ids = array_merge($not_allowed_Ids, $id_array);
            }
        }
        $attached_Ids = array_merge($attached_Ids, $not_allowed_Ids);

        $available_specialities = Speciality::find()
            ->active()
            ->andWhere(['not in', 'id', $attached_Ids])
            ->andWhere(['campaign_code' => $application->type->campaign->code])
            ->andWhere(['receipt_allowed' => 1])
            ->all();

        $department = Speciality::find()
            ->select(['faculty_code', 'faculty_name'])
            ->active()
            ->andWhere(['campaign_code' => $application->type->campaign->code])
            ->asArray()
            ->all();
        $department_array = ArrayHelper::map($department, 'faculty_code', 'faculty_name');

        $finance = Speciality::find()
            ->select(['finance_code', 'finance_name'])
            ->active()
            ->andWhere(['campaign_code' => $application->type->campaign->code])
            ->asArray()
            ->all();
        $finance_array = ArrayHelper::map($finance, 'finance_code', 'finance_name');

        $eduform = Speciality::find()
            ->select(['eduform_code', 'eduform_name'])
            ->active()
            ->andWhere(['campaign_code' => $application->type->campaign->code])
            ->asArray()
            ->all();
        $eduform_array = ArrayHelper::map($eduform, 'eduform_code', 'eduform_name');

        $groups = Speciality::find()
            ->select(['group_code', 'group_name'])
            ->active()
            ->andWhere(['campaign_code' => $application->type->campaign->code])
            ->asArray()
            ->all();
        $groups_array = ArrayHelper::map($groups, 'group_code', 'group_name');

        $region = null;
        $region_code = '0';
        $area = null;
        $area_code = '0';
        $city = null;
        $city_code = '0';
        $village = null;
        $village_code = '0';

        if ($questionary->addressData->region_id != null) {
            $region = Fias::findOne(['id' => $questionary->addressData->region_id, 'archive' => 0]);
            $region_code = $region->region_code;
        }
        if ($questionary->addressData->area_id != null) {
            $area = Fias::findOne(['id' => $questionary->addressData->area_id, 'archive' => 0]);
            $area_code = $area->area_code;
        }
        if ($questionary->addressData->city_id != null) {
            $city = Fias::findOne(['id' => $questionary->addressData->city_id, 'archive' => 0]);
            $city_code = $city->city_code;
        }
        if ($questionary->addressData->village_id != null) {
            $village = Fias::findOne(['id' => $questionary->addressData->village_id, 'archive' => 0]);
            $village_code = $village->village_code;
        }

        $areas = Fias::findByCodes('2', $region_code, $area_code, $city_code, $village_code);
        $cities = Fias::findByCodes('3', $region_code, $area_code, $city_code, $village_code);
        $villages = Fias::findByCodes('4', $region_code, $area_code, $city_code, $village_code);
        $streets = Fias::findByCodes('5', $region_code, $area_code, $city_code, $village_code);

        $areas_array = ArrayHelper::map($areas, 'id', 'name');
        if (sizeof($areas_array) == 0) {
            $areas_array = ['' => 'Сначала выберите регион'];
        }

        $cities_array = ArrayHelper::map($cities, 'id', 'name');
        if (sizeof($cities_array) == 0) {
            $cities_array = ['' => 'Сначала выберите регион'];
        }

        $villages_array = ArrayHelper::map($villages, 'id', 'name');

        if (sizeof($villages_array) == 0) {
            $villages_array = ['' => 'Сначала выберите регион'];
        }

        $streets_array = [];
        foreach ($streets as $street) {
            $streets_array[$street->id] = $street->fullname;
        }
        if (sizeof($streets_array) == 0) {
            $streets_array = ['' => 'Сначала выберите город'];
        }

        $exam_disciplines = EgeDiscipline::find()
            ->select(['id', 'discipline_name'])
            ->active()
            ->andWhere(['campaign_code' => $application->type->campaign->code])
            ->asArray()
            ->all();
        $exams_array = ArrayHelper::map($exam_disciplines, 'id', 'discipline_name');

        $ind_achs = IndividualAchievement::findAll(['user_id' => $application->user_id]);

        $printForms = [];

        // create PersonalReceipt print form
        $personalReceiptPrintForm = new PrintForm();
        $personalReceiptPrintForm->model = $application;
        $personalReceiptPrintForm->type = PrintForm::TYPE_PERSONAL_RECEIPT;
        if ($personalReceiptPrintForm->checkFileExist()) {
            $printForms[] = $personalReceiptPrintForm;
        }

        $code = '';
        $code_message = '';
        if (strpos($error_text, '"Физическое лицо" не заполнено') !== false) {
            if ($code = Yii::$app->authentication1CManager->getAbiturientCode($personal_data->passport_number, $personal_data->passport_series)) {
                $code_message = 'У абитуриента с такими паспортными данными есть номер Физ. лица (' . $code . ') в базе 1С. Хотите ли Вы сопоставить данного абитуриента с этим Физ. лицом.';
            }
        }

        return $this->render("moderate", [
            'application' => $application,
            'questionary' => $questionary,
            'cities_array' => $cities_array,
            'streets_array' => $streets_array,
            'areas_array' => $areas_array,
            'villages_array' => $villages_array,
            'appid' => $id,
            'hasErrors' => $hasErrors,
            'available_specialities' => $available_specialities,
            'department_array' => $department_array,
            'finance_array' => $finance_array,
            'eduform_array' => $eduform_array,
            'groups_array' => $groups_array,
            'ege_array' => $exams_array,
            'error' => $error,
            'ind_achs' => $ind_achs,
            'add_errors' => $add_errors,
            'errors' => $errors,
            'error_text' => $error_text,
            'printForms' => $printForms,
            'code' => $code,
            'code_message' => $code_message
        ]);
    }

    public function actionDecline($id)
    {
        $request = Yii::$app->request;
        $application = BachelorApplication::findOne((int)$id);
        if ($request->isPost && $application->block_status != BachelorApplication::BLOCK_STATUS_ENABLED) {
            Yii::$app->session->set('unblocked' . $application->id, '1');
            return $this->redirect(Url::to(['sandbox/moderate', 'id' => $id]), 302);
        }
        $questionary = AbiturientQuestionary::findOne(['user_id' => $application->user_id]);
        if ($request->isPost) {
            $application->load(Yii::$app->request->post());
            $application->status = BachelorApplication::STATUS_NOTAPPROVED;
            $application->block_status = BachelorApplication::BLOCK_STATUS_DISABLED;
            $application->approver_id = Yii::$app->user->identity->id;
            $application->approved_at = time();
            if ($application->validate()) {
                $application->save();
                $application->addModerateHistory();
                ApplicationHistory::deleteAll(['application_id' => $application->id]);
                Yii::$app->notifier->notifyAboutDeclineApplication($application->user_id, $application->moderator_comment);
            }
            if ($application->user->guid == null) {
                $questionary->status = AbiturientQuestionary::STATUS_NOTAPPROVED;
            }
            if ($questionary->validate()) {
                $questionary->save();
            }
        }
        return $this->redirect('/sandbox/index', 302);
    }

    public function actionRemovespeciality($id)
    {
        if (Yii::$app->request->isPost) {
            $spec = BachelorSpeciality::findOne((int)Yii::$app->request->post("id"));
            $priority = $spec->priority;
            $app_id = $spec->application->id;
            $count = BachelorSpeciality::find()->where(['application_id' => $app_id])->count();
            if ($count == 1) {
                return $this->redirect(Url::to(['sandbox/moderate', 'id' => $id]), 302);
            }

            $spec->delete();
            $specs = BachelorSpeciality::find()->where(['application_id' => $app_id])
                ->andWhere(['>', 'priority', $priority])->all();

            foreach ($specs as $sp) {
                $sp->priority = $sp->priority - 1;
                $sp->save();
            }

        }

        return $this->redirect(Url::to(['sandbox/moderate', 'id' => $id]), 302);
    }

    public function actionRemoveresult($id)
    {
        if (Yii::$app->request->isPost) {
            $result = EgeResult::findOne((int)Yii::$app->request->post("id"));
            $year_id = $result->egeyear_id;
            if ($result->status != 1) {
                $result->delete();
                $year = EgeYear::findOne(['id' => $year_id]);
                if (sizeof($year->results) == 0) {
                    $year->delete();
                }
            }
        }
        return $this->redirect(Url::to(['sandbox/moderate', 'id' => $id]), 302);
    }

    public function actionUpdateresult($id)
    {
        if (Yii::$app->request->isPost) {
            $post_result = Yii::$app->request->post('EgeResult');
            $result = EgeResult::findOne((int)$post_result['id']);
            $result->discipline_id = (int)$post_result['discipline_id'];

            if (isset($post_result['exam_form_id'])) {
                $result->exam_form_id = $post_result['exam_form_id'];
            }

            if (isset($post_result['discipline_points']) && $result->examForm->readonly == 0) {
                $result->discipline_points = $post_result['discipline_points'];
            } elseif ($result->readonly !== 1) {
                $result->discipline_points = null;
            }

            $year_number = (int)Yii::$app->request->post('year');
            $year = EgeYear::findOne(['year_number' => $year_number, 'application_id' => (int)$id]);
            if (isset($year)) {
                $result->egeyear_id = $year->id;
            } else {
                $year = new EgeYear();
                $year->application_id = (int)$id;
                $year->year_number = (string)$year_number;
                if ($year->validate()) {
                    $year->save();
                    $result->egeyear_id = $year->id;
                }
            }
            if ($result->validate()) {
                $result->save();
            }
        }
        return $this->redirect(Url::to(['sandbox/moderate', 'id' => $id]), 302);
    }

    public function actionAddresult($id)
    {
        if (Yii::$app->request->isPost && strlen(Yii::$app->request->post('discipline_id')) > 0) {
            $result = new EgeResult();
            $result->discipline_id = (int)Yii::$app->request->post('discipline_id');

            if (Yii::$app->request->post('exam_form_id') != null) {
                $result->exam_form_id = Yii::$app->request->post('exam_form_id');
            }

            if (Yii::$app->request->post('discipline_points') != null && Yii::$app->request->post('exam_form_id') != null
                && $result->examForm->readonly == 0
            ) {
                $result->discipline_points = Yii::$app->request->post('discipline_points');
            }

            $year_number = (int)Yii::$app->request->post('discipline_year');
            $year = EgeYear::findOne(['year_number' => $year_number, 'application_id' => (int)$id]);
            if (isset($year)) {
                $result->egeyear_id = $year->id;
            } else {
                if ((int)$year_number < 1990 || (int)$year_number > (int)date("Y")) {
                    return $this->redirect(Url::to(['sandbox/moderate', 'id' => $id]), 302);
                }
                $year = new EgeYear();
                $year->application_id = (int)$id;
                $year->year_number = (string)$year_number;
                if ($year->validate()) {
                    $year->save();
                    $result->egeyear_id = $year->id;
                }
            }
            if ($result->validate()) {
                $result->save();
            }
        }
        return $this->redirect(Url::to(['sandbox/moderate', 'id' => $id]), 302);
    }

    public function actionAddspecialities($id)
    {
        $errors = [];
        if (Yii::$app->request->isPost) {
            $application_id = (int)$id;
            if (Yii::$app->request->post('spec') != null) {
                $i = 0;
                foreach (Yii::$app->request->post('spec') as $speciality_id) {
                    $haveErrors = false;
                    $added_count = BachelorSpeciality::find()->where(['application_id' => $application_id])->count();
                    $adding_spec = Speciality::findOne(['id' => (int)$speciality_id, 'archive' => 0]);
                    $commecial = BachelorSpeciality::find()->joinWith('speciality')
                        ->where(['application_id' => $application_id])
                        ->andWhere(['dictionary_speciality.finance_code' => '2'])
                        ->count();
                    $added = BachelorSpeciality::find()->where(['application_id' => $application_id])->all();
                    $codes = [];
                    foreach ($added as $spec) {
                        $codes[] = $spec->speciality->speciality_human_code;
                    }
                    $unique_codes = array_unique($codes);
                    $canaddIfNotCommercial = true;
                    if (sizeof($unique_codes) >= 3 && !in_array($adding_spec->speciality_human_code, $unique_codes)) {
                        $canaddIfNotCommercial = false;
                    }
                    $canAddIfCommercial = true;
                    if ($commecial > 0 && $adding_spec->finance_code == "2") {
                        $canAddIfCommercial = false;
                    }

                    if ($adding_spec != null && $canAddIfCommercial && $canaddIfNotCommercial && $adding_spec->receipt_allowed == 1) {
                        $bachelor_spec = new BachelorSpeciality();
                        $bachelor_spec->speciality_id = $adding_spec->id;
                        $bachelor_spec->application_id = $application_id;
                        $bachelor_spec->priority = $added_count + 1;

                        $check_errors = $bachelor_spec->checkBalls();

                        if (sizeof($check_errors) > 0) {
                            $haveErrors = true;
                            $errors[$i] = [
                                'name' => $bachelor_spec->speciality->speciality_human_code . ' ' . $bachelor_spec->speciality->speciality_name,
                                'errors' => $check_errors,
                            ];
                        }
                        if ($bachelor_spec->validate() && !$haveErrors) {
                            $bachelor_spec->save();
                        }
                    }
                    $i++;
                }
            }
        }
        if (sizeof($errors) > 0) {
            Yii::$app->session->set('add_errors', json_encode($errors));
        }
        return $this->redirect(Url::to(['sandbox/moderate', 'id' => $id]), 302);
    }

    public function actionUnblock($id)
    {
        $application = BachelorApplication::findOne((int)$id);
        if ($application != null) {
            $application->block_status = BachelorApplication::BLOCK_STATUS_DISABLED;
            $application->blocker_id = null;
            if ($application->validate()) {
                $application->save();
            }
        }
        return $this->redirect('/sandbox/index', 302);
    }

    public function actionViewQuestionary($id)
    {
        $questionary = AbiturientQuestionary::findOne((int)$id);
        $ind_achs = [];
        if ($questionary != null) {
            $ind_achs = IndividualAchievement::findAll(['user_id' => $questionary->user_id]);
        }

        return $this->render("questionary_view", [
            'questionary' => $questionary,
            'ind_achs' => $ind_achs,
        ]);
    }

    public function actionView($id)
    {

        $application = BachelorApplication::findOne((int)$id);
        $questionary = AbiturientQuestionary::findOne(['user_id' => $application->user_id]);

        $attached_Ids = array_map(create_function('$o', 'return $o->speciality_id;'), $application->specialities);
        $available_specialities = Speciality::find()->active()->andWhere(['not in', 'id', $attached_Ids])->all();
        $department = Speciality::find()->select(['faculty_code', 'faculty_name'])->active()->asArray()->all();
        $department_array = ArrayHelper::map($department, 'faculty_code', 'faculty_name');

        $finance = Speciality::find()->select(['finance_code', 'finance_name'])->active()->asArray()->all();
        $finance_array = ArrayHelper::map($finance, 'finance_code', 'finance_name');

        $eduform = Speciality::find()->select(['eduform_code', 'eduform_name'])->active()->asArray()->all();
        $eduform_array = ArrayHelper::map($eduform, 'eduform_code', 'eduform_name');

        $groups = Speciality::find()->select(['group_code', 'group_name'])->active()->asArray()->all();
        $groups_array = ArrayHelper::map($groups, 'group_code', 'group_name');

        $region = null;
        $area = null;
        $city = null;
        if ($questionary->addressData->region_id != null) {
            $region = Fias::findOne(['id' => $questionary->addressData->region_id, 'archive' => 0]);
        }
        if ($questionary->addressData->area_id != null) {
            $area = Fias::findOne(['id' => $questionary->addressData->area_id, 'archive' => 0]);
        }
        if ($questionary->addressData->city_id != null) {
            $city = Fias::findOne(['id' => $questionary->addressData->city_id, 'archive' => 0]);
        }

        $areas = [];
        if ($questionary->addressData->region_id == null) {
            $areas = [];
        } else {
            $areas = Fias::find()
                ->active()
                ->andFilterWhere(['region_code' => $region->region_code])
                ->andFilterWhere(['address_element_type' => '2'])
                ->select(['id', 'name'])
                ->asArray()
                ->all();
        }
        $areas_array = ArrayHelper::map($areas, 'id', 'name');

        if (sizeof($areas_array) == 0) {
            $areas_array = ['' => 'Сначала выберите регион'];
        }

        $cities = [];
        if ($questionary->addressData->region_id == null) {
            $cities = [];
        } else {
            $region = Fias::findOne(['id' => $questionary->addressData->region_id, 'archive' => 0]);
            if ($questionary->addressData->area_id != null) {
                $cities = Fias::find()
                    ->active()
                    ->andFilterWhere(['region_code' => $region->region_code])
                    ->andFilterWhere(['area_code' => $area->area_code])
                    ->andFilterWhere(
                        ['or',
                            ['address_element_type' => '3'],
                            ['address_element_type' => '4']]
                    )
                    ->select(['id', 'name'])
                    ->asArray()
                    ->all();
            } else {
                $cities = Fias::find()
                    ->active()
                    ->andFilterWhere(['region_code' => $region->region_code])
                    ->andFilterWhere(
                        ['or',
                            ['address_element_type' => '3'],
                            ['address_element_type' => '4']])
                    ->select(['id', 'name'])
                    ->asArray()
                    ->all();
            }
        }
        $cities_array = ArrayHelper::map($cities, 'id', 'name');

        if (sizeof($cities_array) == 0) {
            $cities_array = ['' => 'Сначала выберите регион'];
        }

        $streets = [];
        if ($questionary->addressData->city_id != null) {
            if ($city->address_element_type == '3') {
                $streets = Fias::find()
                    ->active()
                    ->andFilterWhere(['region_code' => $city->region_code])
                    ->andFilterWhere(['city_code' => $city->city_code])
                    ->andFilterWhere(['address_element_type' => '5'])
                    ->all();
            } else {
                $streets = Fias::find()
                    ->active()
                    ->andFilterWhere(['region_code' => $city->region_code])
                    ->andFilterWhere(['village_code' => $city->village_code])
                    ->andFilterWhere(['address_element_type' => '5'])
                    ->all();
            }
        } elseif ($questionary->addressData->area_id != null) {
            $streets = Fias::find()
                ->active()
                ->andFilterWhere(['region_code' => $area->region_code])
                ->andFilterWhere(['area_code' => $area->area_code])
                ->andFilterWhere(['address_element_type' => '5'])
                ->all();
        } elseif ($questionary->addressData->region_id != null) {
            $streets = Fias::find()
                ->active()
                ->andFilterWhere(['region_code' => $region->region_code])
                ->andFilterWhere(['address_element_type' => '5'])
                ->all();
        }
        $streets_array = [];
        foreach ($streets as $street) {
            $streets_array[$street->id] = $street->fullname;
        }
        if (sizeof($streets_array) == 0) {
            $streets_array = ['' => 'Сначала выберите город'];
        }

        $eges = EgeDiscipline::find()->active()->select(['id', 'discipline_name'])->asArray()->all();
        $ege_array = ArrayHelper::map($eges, 'id', 'discipline_name');

        $ind_achs = IndividualAchievement::findAll(['user_id' => $application->user_id]);

        $printForms = [];


        // create PersonalReceipt print form
        $personalReceiptPrintForm = new PrintForm();
        $personalReceiptPrintForm->model = $application;
        $personalReceiptPrintForm->type = PrintForm::TYPE_PERSONAL_RECEIPT;
        if ($personalReceiptPrintForm->checkFileExist()) {
            $printForms[] = $personalReceiptPrintForm;
        }

        return $this->render("view", [
            'application' => $application,
            'questionary' => $questionary,
            'cities_array' => $cities_array,
            'streets_array' => $streets_array,
            'areas_array' => $areas_array,
            'id' => $id,
            'available_specialities' => $available_specialities,
            'department_array' => $department_array,
            'finance_array' => $finance_array,
            'eduform_array' => $eduform_array,
            'groups_array' => $groups_array,
            'ege_array' => $ege_array,
            'ind_achs' => $ind_achs,
            'printForms' => $printForms,
        ]);
    }

    private function formatError($message)
    {
        if (mb_strpos($message, 'по причине: ; ') !== false)
            return mb_substr($message, mb_strpos($message, 'по причине: ; ') + mb_strlen('по причине: ; '));

        return $message;
    }

    private function getListOfAdmissionCampaign($userId) {
        return (new Query())
            ->select('admission_campaign.code, application_type.name')
            ->from('application_type')
            ->leftJoin('{{%moderate_admission_campaign}}', 'application_type.id = moderate_admission_campaign.application_type_id')
            ->leftJoin('admission_campaign', 'admission_campaign.id = application_type.campaign_id')
            ->where(['rbac_auth_assignment_user_id' => $userId])
            ->all();
    }

    public function actionGetAllAttachments($application_id) {
        $application = BachelorApplication::findOne($application_id);

        if (empty($application)) {
            return false;
        }

        $questionary = AbiturientQuestionary::findOne(['user_id' => $application->user_id]);

        if (empty($questionary)) {
            return false;
        }

        $zip = new \ZipArchive();

        $personalData = $questionary->personalData;
        $filename = $personalData->lastname . '_' . $personalData->firstname . '_' . $personalData->middlename . ".zip";

        if ($zip->open($filename, \ZipArchive::CREATE && \ZipArchive::OVERWRITE) !== true) {
            return -1;
        }

        $user = Yii::$app->user->identity;

        foreach ($questionary->attachments as $attachment) {
            if ($attachment->checkAccess($user)) {
                if (file_exists($attachment->getAbsPath())) {
                    $zip->addFile($attachment->getAbsPath(), $attachment->attachmentType->name . '.' . $attachment->extension);
                }
            }
        }

        foreach ($application->allAttachments as $attachment) {
            if ($attachment->checkAccess($user)) {
                if (file_exists($attachment->getAbsPath())) {
                    $zip->addFile($attachment->getAbsPath(), $attachment->attachmentType->name . '.' . $attachment->extension);
                }
            }
        }

        if ($zip->numFiles > 0) {
            $pathToZipArchive = $zip->filename;

            $zip->close();



            Yii::$app->response->sendFile($pathToZipArchive, $filename)->on(\yii\web\Response::EVENT_AFTER_SEND, function($event) {
                unlink($event->data);
            }, $pathToZipArchive);
        }
    }

    public function actionBind($id, $code) {
        if ($user = User::findOne($id)) {
            $user->guid = $code;
            if ($user->validate()) {
                $user->save();
                $session = \Yii::$app->session;
                $session->setFlash('bind', 'Абитуриент успешно сопоставлен с Физ. лицом. Можно "Одобрить" заявление.');
            }
        }

        return $this->redirect(Url::to(['sandbox/moderate', 'id' => $id]), 302);
    }
}
