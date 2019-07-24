<?php

namespace common\modules\abiturient\controllers;

use common\models\Attachment;
use common\models\AttachmentType;
use common\models\dictionary\Fias;
use common\models\dictionary\FiasDoma;
use common\modules\abiturient\models\AbiturientQuestionary;
use common\modules\abiturient\models\AddressData;
use common\modules\abiturient\models\bachelor\ApplicationHistory;
use common\modules\abiturient\models\bachelor\ApplicationType;
use common\modules\abiturient\models\bachelor\BachelorApplication;
use common\modules\abiturient\models\IndividualAchievement;
use common\modules\abiturient\models\PassportData;
use common\modules\abiturient\models\PersonalData;
use Yii;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\UploadedFile;

/**
 * Site controller
 */
class AbiturientController extends Controller
{

    public function getViewPath()
    {
        return \Yii::getAlias('@common/modules/abiturient/views/abiturient');
    }

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => ['index', 'questionary', 'applications',
                            'removeapplication', 'test', 'city', 'street',
                            'postalindex', 'update', 'ialist', 'addia', 'area', 'update-contact', 'iatypes', 'ia-update', 'village'],
                        'allow' => true,
                        'roles' => ['abiturient']
                    ],
                    [
                        'actions' => ['city', 'street', 'postalindex', 'area', 'village'],
                        'allow' => true,
                        'roles' => ['manager']
                    ],
                ],

                'denyCallback' => function () {
                    $this->redirect('/');
                }
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

    public function actionIndex()
    {
        $user = \Yii::$app->user->identity;
        if ($user->canViewStep('my-applications')) {
            return $this->redirect('/abiturient/applications', 302);
        }
        return $this->render("index");
    }

    public function actionUpdate()
    {
        $user = \Yii::$app->user->identity;
        AbiturientQuestionary::UpdateStatus($user);
        return $this->redirect('/abiturient/questionary', 302);
    }

    public function actionIaUpdate()
    {
        $user = \Yii::$app->user->identity;
        if ($user->guid != null) {
            $user->getIaFrom1C();
        }

        return $this->redirect('/abiturient/ialist', 302);
    }

    public function actionQuestionary()
    {
        $user = \Yii::$app->user->identity;

        $questionary = $user->abiturientQuestionary;
        if ($user != null && $user->guid != null && $questionary == null) { //студент решил стать абитуриентом и в БД нет инф-и
            AbiturientQuestionary::UpdateStatus($user);
            return $this->redirect('/abiturient/questionary', 302);
        }

        if (!$user->canMakeStep('questionary')) {
            return $this->redirect('/abiturient/index', 302);
        }
        $questionary = null;
        $personal_data = null;
        $passport_data = null;
        $address_data = null;

        $request = Yii::$app->request;
        $isSaved = false;
        $hasErrors = false;
        $attachments = [];
        $isAttachmentsAdded = false;
        $attachmentErrors = [];

        if ($user->abiturientQuestionary != null) {
            $questionary = $user->abiturientQuestionary;
            if ($questionary->status == AbiturientQuestionary::STATUS_SENDED && $user->guid == null && !Yii::$app->configurationManager->sandboxEnabled) {
                $isSaved = false;
                $hasErrors = true;
            }
            if ($request->isPost && !$questionary->canEdit()) {
                return $this->redirect('/abiturient/questionary', 302);
            }
            if ($questionary->personalData != null) {
                if (!$request->isPost) {
                    $personal_data = $questionary->personalData;
                } else {
                    $questionary->personalData->load(Yii::$app->request->post());
                    $personal_data = $questionary->personalData;
                    if (Yii::$app->request->post('user_email') != null && strlen(Yii::$app->request->post('user_email')) > 0 && $user->email != Yii::$app->request->post('user_email')) {
                        $validator = new \yii\validators\EmailValidator();
                        $error = '';
                        if ($validator->validate(Yii::$app->request->post('user_email'), $error)) {
                            $user->email = Yii::$app->request->post('user_email');
                            $user->save();
                        }
                    }
                }
            } else {
                $personal_data = new PersonalData();
                if ($request->isPost) {
                    $personal_data->load(Yii::$app->request->post());
                }
            }

            if ($questionary->passportData != null) {
                if (!$request->isPost) {
                    $passport_data = $questionary->passportData;
                } else {
                    $questionary->passportData->load(Yii::$app->request->post());
                    $passport_data = $questionary->passportData;
                }
            } else {
                $passport_data = new PassportData();
                $passport_data->questionary_id = $user->abiturientQuestionary->id;
                if ($request->isPost) {
                    $passport_data->load(Yii::$app->request->post());
                }
            }

            if ($questionary->addressData != null) {
                if (!$request->isPost) {
                    $address_data = $questionary->addressData;
                } else {
                    $questionary->addressData->load(Yii::$app->request->post());
                    if ($questionary->addressData->area_id == "null") {
                        $questionary->addressData->area_id = null;
                    }
                    $questionary->addressData->cleanUnusedAttributes();
                    $address_data = $questionary->addressData;
                    if ($address_data->street_id != null) {
                        $street = Fias::findOne(['id' => $address_data->street_id, 'archive' => 0]);
                        $address_data->kladr_code = $street->code;
                    }
                }
            } else {
                $address_data = new AddressData();
                $address_data->questionary_id = $user->abiturientQuestionary->id;
                if ($request->isPost) {
                    $address_data->load(Yii::$app->request->post());
                    $address_data->cleanUnusedAttributes();
                    if ($address_data->country != null && $address_data->country->code != Yii::$app->configurationManager->getCode('russia_code')) {
                        $address_data->not_found = 1;
                    }
                    if ($address_data->street_id != null) {
                        $street = Fias::findOne(['id' => $address_data->street_id, 'archive' => 0]);
                        $address_data->kladr_code = $street->code;
                    }
                }
            }

            $types_to_add = AttachmentType::GetNotExistingAttachmentTypesToAdd($questionary->attachments, AttachmentType::RELATED_ENTITY_QUESTIONARY);
            foreach ($types_to_add as $type_to_add) {
                $attachment = new Attachment();
                $attachment->questionary_id = $questionary->id;
                $attachment->attachment_type_id = $type_to_add->id;
                $attachments[] = $attachment;
            }
            $attachments = array_merge($attachments, $questionary->attachments);
            usort($attachments, function ($a, $b) {
                return strcmp($a->attachment_type_id, $b->attachment_type_id);
            });
        } else {
            $questionary = new AbiturientQuestionary();
            $questionary->user_id = $user->id;
            $personal_data = new PersonalData();
            $passport_data = new PassportData();
            $address_data = new AddressData();

            $types_to_add = AttachmentType::GetAttachmentTypesToAdd(AttachmentType::RELATED_ENTITY_QUESTIONARY);
            foreach ($types_to_add as $type_to_add) {
                $attachment = new Attachment();
                $attachment->attachment_type_id = $type_to_add->id;
                $attachments[] = $attachment;
            }

            if (!$request->isPost) {
                $questionary->status = AbiturientQuestionary::STATUS_CREATED;
            } else {
                $personal_data->load(Yii::$app->request->post());
                $passport_data->load(Yii::$app->request->post());
                $address_data->load(Yii::$app->request->post());

                if (Yii::$app->request->post('user_email') != null && strlen(Yii::$app->request->post('user_email')) > 0 && $user->email != Yii::$app->request->post('user_email')) {
                    $validator = new \yii\validators\EmailValidator();
                    $error = '';
                    if ($validator->validate(Yii::$app->request->post('user_email'), $error)) {
                        $user->email = Yii::$app->request->post('user_email');
                        $user->save();
                    }
                }
                $address_data->cleanUnusedAttributes();
                if ($address_data->street_id != null) {
                    $street = Fias::findOne(['id' => $address_data->street_id, 'archive' => 0]);
                    $address_data->kladr_code = $street->code;
                }
            }
        }

        if ($request->isPost && $personal_data->validate() && $questionary->validate()
            && $passport_data->validate() && $address_data->validate()) {
            $questionary->status = AbiturientQuestionary::STATUS_CREATED;
            $questionary->user_id = $user->id;
            $questionary->save();
            $personal_data->questionary_id = $questionary->id;
            $personal_data->save();

            $passport_data->questionary_id = $questionary->id;
            $passport_data->save();

            $address_data->questionary_id = $questionary->id;
            $address_data->save();

            $attachments = [];
            $post_files = Yii::$app->request->post("Attachment")['file'];
            $i = 0;
            if ($post_files == null) {
                $post_files = [];
            }
            foreach ($post_files as $post_file) {
                $attachment = new Attachment();
                $attachment->file = UploadedFile::getInstanceByName("Attachment[file][" . $i . "]");

                if (isset(Yii::$app->request->post("Attachment")[$i]) && $attachment->file != null) {
                    $attachment->attachment_type_id = (int)(Yii::$app->request->post("Attachment")[$i]['attachment_type_id']);
                    $existing_attachment = Attachment::findOne(['questionary_id' => $questionary->id, 'attachment_type_id' => $attachment->attachment_type_id]);
                    if ($existing_attachment != null) {
                        Attachment::findOne(['questionary_id' => $questionary->id, 'attachment_type_id' => $attachment->attachment_type_id])->delete();
                    }
                    $attachments[] = $attachment;
                }
                $i++;
            }
            foreach ($attachments as $attachment) {
                $attachment->questionary_id = $questionary->id;
                if (!$attachment->upload()) {
                    $attachment->delete();
                }
            }
            $isSaved = true;

            $q_id = $questionary->id;

            $questionary = AbiturientQuestionary::findOne($q_id);
            if (Yii::$app->configurationManager->sandboxEnabled) {
                if (sizeof($user->applications) > 0) {
                    $isFirst = true;
                    foreach ($user->applications as $app) {
                        if ($isFirst) {
                            $application_history = ApplicationHistory::findOne(['application_id' => $app->id, 'type' => ApplicationHistory::TYPE_QUESTIONARY_CHANGED]);
                            if ($application_history == null) {
                                $application_history = new ApplicationHistory();
                            }
                            $application_history->application_id = $app->id;
                            $application_history->type = ApplicationHistory::TYPE_QUESTIONARY_CHANGED;
                            $application_history->save();
                        }
                        break;
                    }
                }
                $questionary->status = AbiturientQuestionary::STATUS_SENDED;
                $questionary->save();
            } else {
                $stateOf1C = $questionary->pushTo1C();
                if (!$stateOf1C) {
                    $isSaved = false;
                    $hasErrors = true;
                    $questionary->status = AbiturientQuestionary::STATUS_REJECTED_BY1C;
                    $questionary->save();
                } else {
                    $questionary->status = AbiturientQuestionary::STATUS_APPROVED;
                    $questionary->save();
                }
            }
            return $this->redirect('/abiturient/questionary', 302);
        } elseif ($request->isPost) {
            if (!empty($questionary)) {
                $questionary->status = AbiturientQuestionary::STATUS_CREATED;
                $questionary->save();
            }
        }

        $region = null;
        $region_code = null;

        $area = null;
        $areas = [];
        $area_code = null;

        $city = null;
        $cities = [];
        $city_code = null;
        
        $village = null;
        $villages = [];
        $village_code = null;
        
        $streets = [];

        if ($address_data->region_id != null) {
            $region = Fias::findOne(['id' => $address_data->region_id, 'archive' => 0]);
            if ($region != null) {
                $region_code = $region->region_code;
            }
        }
        if ($address_data->area_id != null) {
            $area = Fias::findOne(['id' => $address_data->area_id, 'archive' => 0]);
            if ($area != null) {
                $area_code = $area->area_code;
            }
        }
        if ($address_data->city_id != null) {
            $city = Fias::findOne(['id' => $address_data->city_id, 'archive' => 0]);
            if ($city != null) {
                $city_code = $city->city_code;
            }
        }
        if ($address_data->village_id != null) {
            $village = Fias::findOne(['id' => $address_data->village_id, 'archive' => 0]);
            if ($village != null) {
                $village_code = $village->village_code;
            }
        }

        if ($region_code !== null) {
            $areas = Fias::findByCodes('2', $region_code, $area_code, $city_code, $village_code);
            $cities = Fias::findByCodes('3', $region_code, $area_code, $city_code, $village_code);
            $streets = Fias::findByCodes('5', $region_code, $area_code, $city_code, $village_code);
            $villages = Fias::findByCodes('4', $region_code, $area_code, $city_code, $village_code);
        }

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

        $streets_array = ArrayHelper::map($streets, 'id', 'name');
        if (sizeof($streets_array) == 0) {
            $streets_array = ['' => 'Сначала выберите город'];
        }

        $required_attachments_check = Attachment::checkRequiredExist($questionary->attachments, AttachmentType::GetRequiredIds(AttachmentType::RELATED_ENTITY_QUESTIONARY));
        if (is_array($required_attachments_check) && $questionary->status != AbiturientQuestionary::STATUS_CREATED && $questionary->canEdit()) {
            $types = AttachmentType::find()->where(['in', 'id', $required_attachments_check])->select(['id', 'name'])->asArray()->all();
            $attachmentErrors = ArrayHelper::map($types, 'id', 'name');

            $questionary->status = AbiturientQuestionary::STATUS_CREATED;
            $questionary->save();
        } else {
            $isAttachmentsAdded = true;
        }

        $hasApplicationType = (bool)ApplicationType::find()->active()->count();

        $canEdit = (new AbiturientQuestionary)->canEditQuestionary();

        return $this->render(
            "questionary",
            [
                'questionary' => $questionary,
                'personal_data' => $personal_data,
                'passport_data' => $passport_data,
                'address_data' => $address_data,
                'areas_array' => $areas_array,
                'cities_array' => $cities_array,
                'villages_array' => $villages_array,
                'streets_array' => $streets_array,
                'isSaved' => $isSaved,
                'hasErrors' => $hasErrors,
                'attachments' => $attachments,
                'isAttachmentsAdded' => $isAttachmentsAdded,
                'attachmentErrors' => $attachmentErrors,
                'isPost' => Yii::$app->request->isPost,
                'hasApplicationType' => $hasApplicationType,
                'canEdit' => $canEdit,
            ]
        );
    }

    public function actionApplications()
    {
        $user = \Yii::$app->user->identity;
        if (!$user->canMakeStep('my-applications')) {
            return $this->redirect('/abiturient/index', 302);
        }
        $applications = $user->applications;
        foreach ($applications as $application) {
            $application->checkUpdateSpecialities();
            $application->checkOrder();
        }

        return $this->render("applications", ['applications' => $applications]);
    }

    public function actionRemoveapplication()
    {
        $error = false;
        if (Yii::$app->request->isPost) {
            $user = \Yii::$app->user->identity;
            $application = BachelorApplication::findOne((int)Yii::$app->request->post("appid"));
            if ($application == null || ($application != null && $user->id != $application->user_id)) {
                return $this->redirect('/abiturient/applications', 302);
            }
            if ($application->canEdit()) {
                if ($user->guid != null) {
                    if ($application->removeApplicationFrom1C()) {
                        $application->delete();
                    } else {
                        $error = true;
                    }
                } else {
                    $application->delete();
                }
            }
        }

        /** need to provide deletion error to user via session? **/

        return $this->redirect('/abiturient/applications', 302);
    }

    public function actionCity()
    {
        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents']; /* area */
            $params = $_POST['depdrop_params']; /* region */
            if ($params != null) {
                $parent_id = (int)$params[0];
                $region = Fias::findOne(['id' => $parent_id, 'archive' => 0]);
                if ($parents[0] != '' && $parents[0] != 'null' && $parents[0] != null && $parents[0] != "Загрузка ...") {
                    $area_id = (int)$parents[0];
                    $area = Fias::findOne(['id' => $area_id, 'archive' => 0]);
                    $area_code = $area->area_code;
                } else { //район по умолчанию ноль, если не выбран
                    $area_code = 0;
                }
                $cities = Fias::find()
                    ->active()
                    ->andFilterWhere(['region_code' => $region->region_code])
                    ->andFilterWhere(['area_code' => $area_code])
                    ->andFilterWhere(['address_element_type' => '3'])
                    ->select(['id', 'name'])
                    ->asArray()
                    ->all();
                return json_encode(['output' => $cities, 'selected' => '']);
            } else {
                return json_encode(['output' => '', 'selected' => '']);
            }
        } else {
            return json_encode(['output' => '', 'selected' => '']);
        }
    }

    public function actionVillage()
    {
        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents']; /* area and city */
            $params = $_POST['depdrop_params']; /* region */

            if ($params != null) {
                $parent_id = (int)$params[0];
                $region = Fias::findOne(['id' => $parent_id, 'archive' => 0]);
                if ($parents[0] != '' && $parents[0] != 'null' && $parents[0] != null && $parents[0] != "Загрузка ...") {
                    if ($parents[1] != '' && $parents[1] != 'null' && $parents[1] != null && $parents[1] != "Загрузка ...") {
                        $area_id = (int)$parents[0];
                        $area = Fias::findOne(['id' => $area_id, 'archive' => 0]);
                        $city_id = (int)$parents[1];
                        $city = Fias::findOne(['id' => $city_id, 'archive' => 0]);
                        $villages = Fias::find()
                            ->active()
                            ->andFilterWhere(['region_code' => $region->region_code])
                            ->andFilterWhere(['area_code' => $area->area_code])
                            ->andFilterWhere(['city_code' => $city->city_code])
                            ->andFilterWhere(['address_element_type' => '4'])
                            ->select(['id', 'name'])
                            ->asArray()
                            ->all();
                    } else {
                        $area_id = (int)$parents[0];
                        $area = Fias::findOne(['id' => $area_id, 'archive' => 0]);
                        $villages = Fias::find()
                            ->active()
                            ->andFilterWhere(['region_code' => $region->region_code])
                            ->andFilterWhere(['area_code' => $area->area_code])
                            ->andFilterWhere(['city_code' => '0'])
                            ->andFilterWhere(['address_element_type' => '4'])
                            ->select(['id', 'name'])
                            ->asArray()
                            ->all();
                    }
                } else {
                    if ($parents[1] != '' && $parents[1] != 'null' && $parents[1] != null && $parents[1] != "Загрузка ...") {
                        $city_id = (int)$parents[1];
                        $city = Fias::findOne(['id' => $city_id, 'archive' => 0]);
                        $villages = Fias::find()
                            ->active()
                            ->andFilterWhere(['region_code' => $region->region_code])
                            ->andFilterWhere(['city_code' => $city->city_code])
                            ->andFilterWhere(['address_element_type' => '4'])
                            ->select(['id', 'name'])
                            ->asArray()
                            ->all();
                    } else {
                        $villages = Fias::find()
                            ->active()
                            ->andFilterWhere(['region_code' => $region->region_code])
                            ->andFilterWhere(['city_code' => '0'])
                            ->andFilterWhere(['address_element_type' => '4'])
                            ->select(['id', 'name'])
                            ->asArray()
                            ->all();
                    }
                }
                return json_encode(['output' => $villages, 'selected' => '']);
            } else {
                return json_encode(['output' => '', 'selected' => '']);
            }
        } else {
            return json_encode(['output' => '', 'selected' => '']);
        }
    }

    public function actionArea()
    {
        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents'];
            if ($parents != null && $parents[0] != '') {
                $parent_id = (int)$parents[0];
                $region = Fias::findOne(['id' => $parent_id, 'archive' => 0]);
                $areas = Fias::find()
                    ->active()
                    ->andFilterWhere(['region_code' => $region->region_code])
                    ->andFilterWhere(['address_element_type' => '2'])
                    ->select(['id', 'name'])
                    ->asArray()
                    ->all();

                return json_encode(['output' => $areas, 'selected' => '']);
            } else {
                return json_encode(['output' => '', 'selected' => '']);
            }
        } else {
            return json_encode(['output' => '', 'selected' => '']);
        }
    }

    public function actionStreet()
    {
        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents'];
            $params = $_POST['depdrop_params'];
            $city_id = $parents[0];
            $village_id = $parents[1];
            $region_id = $params[0];
            $area_id = $params[1];
            if ($region_id != null && $region_id != '') {
                $citySelected = false;
                $areaSelected = false;
                $villageSelected = false;

                if ($city_id != '' && $city_id != 'null' && $city_id != null && $city_id != "Загрузка ...") {
                    $citySelected = true;
                    $city = Fias::findOne(['id' => $city_id, 'archive' => 0]);
                }
                if ($village_id != '' && $village_id != 'null' && $village_id != null && $village_id != "Загрузка ...") {
                    $villageSelected = true;
                    $village = Fias::findOne(['id' => $village_id, 'archive' => 0]);
                }
                if ($area_id != '' && $area_id != 'null' && $area_id != null && $area_id != "Загрузка ...") {
                    $areaSelected = true;
                    $area = Fias::findOne(['id' => $area_id, 'archive' => 0]);
                }
                if ($citySelected && $villageSelected) {
                    if ($areaSelected) {
                        $streets = Fias::find()
                            ->active()
                            ->andFilterWhere(['address_element_type' => '5'])
                            ->andFilterWhere(['region_code' => $city->region_code])
                            ->andFilterWhere(['area_code' => $area->area_code])
                            ->andFilterWhere(['city_code' => $city->city_code])
                            ->andFilterWhere(['village_code' => $village->village_code])
                            ->select(['id', new \yii\db\Expression("CONCAT(name, ' ', short) as name")])
                            ->asArray()
                            ->all();
                    } else {
                        $streets = Fias::find()
                            ->active()
                            ->andFilterWhere(['address_element_type' => '5'])
                            ->andFilterWhere(['region_code' => $city->region_code])
                            ->andFilterWhere(['area_code' => $village->area_code])
                            ->andFilterWhere(['city_code' => $city->city_code])
                            ->andFilterWhere(['village_code' => $village->village_code])
                            ->select(['id', new \yii\db\Expression("CONCAT(name, ' ', short) as name")])
                            ->asArray()
                            ->all();
                    }
                } elseif ($citySelected && !$villageSelected) {
                    if ($areaSelected) {
                        $streets = Fias::find()
                            ->active()
                            ->andFilterWhere(['address_element_type' => '5'])
                            ->andFilterWhere(['region_code' => $city->region_code])
                            ->andFilterWhere(['area_code' => $area->area_code])
                            ->andFilterWhere(['city_code' => $city->city_code])
                            ->andFilterWhere(['village_code' => $city->village_code])
                            ->select(['id', new \yii\db\Expression("CONCAT(name, ' ', short) as name")])
                            ->asArray()
                            ->all();
                    } else {
                        $streets = Fias::find()
                            ->active()
                            ->andFilterWhere(['address_element_type' => '5'])
                            ->andFilterWhere(['region_code' => $city->region_code])
                            ->andFilterWhere(['area_code' => $city->area_code])
                            ->andFilterWhere(['city_code' => $city->city_code])
                            ->andFilterWhere(['village_code' => $city->village_code])
                            ->select(['id', new \yii\db\Expression("CONCAT(name, ' ', short) as name")])
                            ->asArray()
                            ->all();
                    }
                } elseif ($villageSelected && !$citySelected) {
                    if ($areaSelected) {
                        $streets = Fias::find()
                            ->active()
                            ->andFilterWhere(['address_element_type' => '5'])
                            ->andFilterWhere(['region_code' => $village->region_code])
                            ->andFilterWhere(['area_code' => $area->area_code])
                            ->andFilterWhere(['city_code' => $village->city_code])
                            ->andFilterWhere(['village_code' => $village->village_code])
                            ->select(['id', new \yii\db\Expression("CONCAT(name, ' ', short) as name")])
                            ->asArray()
                            ->all();
                    } else {
                        $streets = Fias::find()
                            ->active()
                            ->andFilterWhere(['address_element_type' => '5'])
                            ->andFilterWhere(['region_code' => $village->region_code])
                            ->andFilterWhere(['area_code' => $village->area_code])
                            ->andFilterWhere(['city_code' => $village->city_code])
                            ->andFilterWhere(['village_code' => $village->village_code])
                            ->select(['id', new \yii\db\Expression("CONCAT(name, ' ', short) as name")])
                            ->asArray()
                            ->all();
                    }
                } elseif ($areaSelected) {
                    $streets = Fias::find()
                        ->active()
                        ->andFilterWhere(['address_element_type' => '5'])
                        ->andFilterWhere(['region_code' => $area->region_code])
                        ->andFilterWhere(['area_code' => $area->area_code])
                        ->select(['id', new \yii\db\Expression("CONCAT(name, ' ', short) as name")])
                        ->asArray()
                        ->all();
                } else {
                    $region = Fias::findOne(['id' => $region_id, 'archive' => 0]);
                    $streets = Fias::find()
                        ->active()
                        ->andFilterWhere(['address_element_type' => "5"])
                        ->andFilterWhere(['region_code' => (string)$region->region_code])
                        ->andFilterWhere(['area_code' => '0'])
                        ->andFilterWhere(['city_code' => '0'])
                        ->andFilterWhere(['village_code' => '0'])
                        ->select(['id', new \yii\db\Expression("CONCAT(name, ' ', short) as name")])
                        ->asArray()
                        ->all();
                }
                return json_encode(['output' => $streets, 'selected' => '']);
            } else {
                return json_encode(['output' => '', 'selected' => '']);
            }
        } else {
            return json_encode(['output' => '', 'selected' => '']);
        }
    }

    public function actionPostalindex()
    {
        if (isset($_POST['sid']) && strlen($_POST['sid']) > 0) {
            $sid = (int)$_POST['sid'];
            $fias_el = Fias::findOne($sid);

            if ($fias_el != null) {
                if (strlen(str_replace(" ", "", $fias_el->zip_code)) > 0) {
                    return $fias_el->zip_code;
                } else {
                    if (!empty($_POST['house'])) {
                        $house = $_POST['house'];
                    } else {
                        $house = '';
                    }

                    if (!empty($_POST['housing'])) {
                        $housing = $_POST['housing'];
                    } else {
                        $housing = '';
                    }

                    $index = FiasDoma::streetIndex($fias_el->code, $house, $housing);

                    if ($index != 0) return $index;

                    $city = null;
                    if ($fias_el->city_code != "0") {
                        $city = Fias::findOne(['region_code' => $fias_el->region_code, 'city_code' => $fias_el->city_code, 'address_element_type' => '3']);
                    } elseif ($fias_el->village_code != "0") {
                        $city = Fias::findOne(['region_code' => $fias_el->region_code, 'village_code' => $fias_el->village_code, 'address_element_type' => '4']);
                    }
                    if ($city != null && strlen(str_replace(" ", "", $city->zip_code)) > 0) {
                        return $city->zip_code;
                    } else {
                        if ($fias_el->area_code != "0") {
                            $area = Fias::findOne(['region_code' => $fias_el->region_code, 'area_code' => $fias_el->area_code, 'address_element_type' => '2']);
                            if (strlen(str_replace(" ", "", $area->zip_code)) > 0) {
                                return $area->zip_code;
                            } else {
                                $region = Fias::findOne(['region_code' => $fias_el->region_code, 'address_element_type' => '1']);
                                if (strlen(str_replace(" ", "", $region->zip_code)) > 0) {
                                    return $region->zip_code;
                                } else {
                                    return "0";
                                }
                            }
                        } else {
                            $region = Fias::findOne(['region_code' => $fias_el->region_code, 'address_element_type' => '1']);
                            if (strlen(str_replace(" ", "", $region->zip_code)) > 0) {
                                return $region->zip_code;
                            } else {
                                return "0";
                            }
                        }
                    }
                }
            } else {
                return '';
            }
        } else {
            return '';
        }
    }

    public function actionIalist()
    {
        $user = \Yii::$app->user->identity;
        if (!$user->canMakeStep('ia-list')) {
            return $this->redirect('/abiturient/index', 302);
        }
        $ind_achs = $user->individualAchievements;
        $hasError = false;
        $error = Yii::$app->session->get('ia_error');

        if ($error == "1") {
            $hasError = true;
            Yii::$app->session->set('ia_error', '0');
        }

        $canEdit = (new AbiturientQuestionary)->canEditQuestionary();

        return $this->render(
            "ialist",
            [
                'ind_achs' => $ind_achs,
                'canEdit' => $canEdit,
                'hasError' => $hasError,
                'user' => $user
            ]
        );
    }

    public function actionIatypes()
    {
        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents'];

            if ($parents != null && $parents[0] != '') {
                $apptype_id = (int)$parents[0];
                $apptype = ApplicationType::findOne(['id' => (int)$apptype_id, 'archive' => 0]);

                //$ia_types = \common\models\dictionary\IndividualAchievementType::find()->where(['campaign_code' => $apptype->campaign->code])->select(['id','name'])->asArray()->all();
                $ia_types = \common\models\dictionary\IndividualAchievementType::find()->active()->andWhere(['campaign_code' => $apptype->campaign->code])->select(['id', 'name'])->asArray()->all();
                if (sizeof($ia_types) > 0) {
                    return json_encode(['output' => $ia_types, 'selected' => $ia_types[0]['id']]);
                }
            }
        }
        return json_encode(['output' => '', 'selected' => '']);
    }

    public function actionAddia()
    {
        $user = \Yii::$app->user->identity;
        if (Yii::$app->request->isPost && $user->canEdit()) {
            $ia = new IndividualAchievement();
            $ia->isFrom1C = false;
            $ia->load(Yii::$app->request->post());
            $ia->file = UploadedFile::getInstance($ia, 'file');
            $ia->user_id = $user->id;
            if (!empty($ia->file)) {
                if (!$ia->upload()) {
                    Yii::$app->session->set('ia_error', '1');
                    return $this->redirect(Url::toRoute(['/abiturient/ialist']), 302);
                }
            } else {
                $ia->save();
            }

            if (sizeof($user->applications) > 0) {
                $application = BachelorApplication::findOne(['user_id' => $user->id]);
                if ($application != null) {
                    $application_history = ApplicationHistory::findOne(['application_id' => $application->id, 'type' => ApplicationHistory::TYPE_INDIVIDUAL_ACH_CHANGED]);
                    if ($application_history == null) {
                        $application_history = new ApplicationHistory();
                    }
                    $application_history->application_id = $application->id;
                    $application_history->type = ApplicationHistory::TYPE_INDIVIDUAL_ACH_CHANGED;
                    $application_history->save();

                    if ($application->status == BachelorApplication::STATUS_APPROVED) {
                        $application->status = BachelorApplication::STATUS_SENDED;
                        $application->sended_at = time();
                        $application->save();
                    }
                }
            }

        }
        return $this->redirect(Url::toRoute('/abiturient/ialist'), 302);
    }

    public function actionUpdateContact()
    {
        $user = \Yii::$app->user->identity;
        if (Yii::$app->request->isPost && $user->guid != null) {
            $email = null;
            $main_phone = null;
            $secondary_phone = null;
            $model = new \frontend\models\UpdateContactForm();
            $model->user = $user;
            if (strlen(Yii::$app->request->post("update_email")) > 0) {
                $model->email = Yii::$app->request->post("update_email");
            }
            if (strlen(Yii::$app->request->post("update_mainphone")) > 0) {
                $model->main_phone = Yii::$app->request->post("update_mainphone");
            }
            if (strlen(Yii::$app->request->post("update_secondaryphone")) > 0) {
                $model->secondary_phone = Yii::$app->request->post("update_secondaryphone");
            }
            $model->save();
            if (sizeof($user->applications) > 0) {
                $application = BachelorApplication::findOne(['user_id' => $user->id]);
                if ($application != null) {
                    $application_history = ApplicationHistory::findOne(['application_id' => $application->id, 'type' => ApplicationHistory::TYPE_QUESTIONARY_CHANGED]);
                    if ($application_history == null) {
                        $application_history = new ApplicationHistory();
                    }
                    $application_history->application_id = $application->id;
                    $application_history->type = ApplicationHistory::TYPE_QUESTIONARY_CHANGED;
                    $application_history->save();

                    if ($application->status == BachelorApplication::STATUS_APPROVED) {
                        $application->status = BachelorApplication::STATUS_SENDED;
                        $application->sended_at = time();
                        $application->save();
                    }
                }
            }

        }
        return $this->redirect(Url::toRoute(['/abiturient/questionary']), 302);
    }
}
