<?php

namespace our_backend\controllers\gameapi;

use Yii;
use our_backend\controllers\Controller;
use common\models\SetlimitModel as selfModel;
use common\models\AgentModel;
use common\helpers\lotteryOrm;
use yii\helpers\ArrayHelper;

class SetlimitController extends Controller {

    public function actionIndex() {
        $data['show'] = $this->showData();

        $request = Yii::$app->request->get();
        $data['agents'] = selfModel::agent($request); // 回显所选代理类型的代理商

        $result = $this->search($request);

        $render = [
            'data' => $data,
            'result' => $result
        ];
        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('index.html', $render);
        } else {
            return $this->render('index.html', $render);
        }
    }

    public function actionAdd() {
        if (!Yii::$app->request->isPost) {
            $data['show'] = $this->showData();

            $request = Yii::$app->request->get();
            $data['agents'] = selfModel::agent($request); // 回显所选代理类型的代理商
            $data['gameplays'] = selfModel::gameplay($request); // 回显所选玩法

            $render = [
                'data' => $data
            ];

            $render['item'] = selfModel::getItem($request);

            if (Yii::$app->request->isAjax) {
                return $this->renderAjax('_form.html', $render);
            } else {
                return $this->render('_form.html', $render);
            }
        } else {
            $request = Yii::$app->request->post();
            $result = $this->addSubmit($request);

            return json_encode($result);
        }
    }

    public function actionAgent() {
        $request = Yii::$app->request->post();
        $result = selfModel::agent($request); // 切换代理类型

        return json_encode($result);
    }

    /*
     * *************************************************************************
     */

    public function search($request) {
        $query = $request;
        unset($query['_pjax']);
        if (empty($query)) {
            return;
        }

        $line_id = isset($request['line_id']) ? trim($request['line_id']) : null;
        $agent_type = isset($request['agent_type']) ? trim($request['agent_type']) : null;
        $agent_id = isset($request['agent_id']) ? trim($request['agent_id']) : null;
        $fc_type = isset($request['fc_type']) ? trim($request['fc_type']) : null;

        if (empty($fc_type)) {
            return;
        }
        if (!empty($agent_type)) {
            if (empty($agent_id)) {
                return;
            }
        }

        $data = selfModel::getSetlimit($request);
        $data = $this->trans($data); // 翻译

        $result['data'] = $data;
        return $result;
    }

    public function addSubmit($request) {
        $iscustom = isset($request['iscustom']) ? trim($request['iscustom']) : null;
        $id = isset($request['id']) ? trim($request['id']) : null;
        $line_id = isset($request['line_id']) ? trim($request['line_id']) : null;
        $agent_type = isset($request['agent_type']) ? trim($request['agent_type']) : null;
        $agent_id = isset($request['agent_id']) ? trim($request['agent_id']) : null;
        $fc_type = isset($request['fc_type']) ? trim($request['fc_type']) : null;
        $fc_name = isset($request['fc_name']) ? trim($request['fc_name']) : null;
        $gameplay = isset($request['gameplay']) ? trim($request['gameplay']) : null;
        $name = isset($request['name']) ? trim($request['name']) : null;
        $sort = !empty($request['sort']) ? trim($request['sort']) : 0;
        $limit_min = isset($request['limit_min']) ? trim($request['limit_min']) : null;
        $single_field_max = isset($request['single_field_max']) ? trim($request['single_field_max']) : null;
        $single_note_max = isset($request['single_note_max']) ? trim($request['single_note_max']) : null;

        $result['ErrorCode'] = 2;
        if (empty($line_id)) {
            $result['ErrorMsg'] = '请选择线路';
            return $result;
        }elseif (empty($fc_type)) {
            $result['ErrorMsg'] = '请选择彩种';
            return $result;
        }elseif ( empty($gameplay)) {
            $result['ErrorMsg'] = '玩法不能为空';
            return $result;
        }elseif (  empty($limit_min) ) {
            $result['ErrorMsg'] = '最小限额不能为空';
            return $result;
        }elseif (  empty($single_field_max) ) {
            $result['ErrorMsg'] = '单笔最大限额不能为空';
            return $result;
        }elseif (empty($single_note_max) ) {
            $result['ErrorMsg'] = '单笔最大限额不能为空';
            return $result;
        }

        if ($limit_min < 0 || $single_field_max < 0 || $single_note_max < 0) {
            $result['ErrorCode'] = 2;
            $result['ErrorMsg'] = '金额错误';
            return $result;
        }

        $cols = [
            'line_id' => $line_id,
            'fc_type' => $fc_type,
            'fc_name' => $fc_name,
            'gameplay' => $gameplay,
            'name' => $name,
            'sort' => $sort,
            'limit_min' => $limit_min,
            'single_field_max' => $single_field_max,
            'single_note_max' => $single_note_max
        ];
        if ($agent_type && $agent_id) {
            $tab = 'agent';
            $logTxt = "代理({$agent_type}:{$agent_id})";
            $where['agent_type'] = $agent_type;
            $where['agent_id'] = $agent_id;
            $cols['agent_type'] = $agent_type;
            $cols['agent_id'] = $agent_id;
        } else {
            $tab = 'line';
            $logTxt = "线路({$line_id})";
        }
        if ($iscustom == 1 || ($iscustom == -1 && $tab == 'line')) {
            $where['line_id'] = $line_id;
            $where['fc_type'] = $fc_type;
            $where['gameplay'] = $gameplay;
            $cols = [
                'limit_min' => $limit_min,
                'single_field_max' => $single_field_max,
                'single_note_max' => $single_note_max
            ];
            $sqlQuery = selfModel::update(selfModel::getTableName($tab), $cols, $where);
            if (!$sqlQuery) {
                $result['ErrorCode'] = 1;
                $result['ErrorMsg'] = '未修改';
                return $result;
            }
        } else {
            $sqlQuery = selfModel::insert(selfModel::getTableName($tab), $cols);
        }

        $old = isset($request['info']) ? trim($request['info']) : null;
        $new = json_encode($cols);
        $info = json_decode($old, true);
        $username = Yii::$app->session->get('uname');
        $remark = $username . " 修改了 {$logTxt} 的限额数据 ";
        $remark .= "<br>旧:最小限额 {$info['limit_min']},单场最大限额 {$info['single_field_max']},单笔最大限额 {$info['single_note_max']} ";
        $remark .= "<br>新:最小限额 {$limit_min},单场最大限额 {$single_field_max},单笔最大限额 {$single_note_max}";
        $this->insertOperateLog($old, $new, $remark);

        // 更新Redis缓存
        $this->resetRedis($request);

        $result['ErrorCode'] = 0;
        $result['ErrorMsg'] = '修改成功';
        return $result;
    }

    // 更新 Redis 缓存
    public function resetRedis($request) {
        $line_id = isset($request['line_id']) ? trim($request['line_id']) : null;
        $agent_type = isset($request['agent_type']) ? trim($request['agent_type']) : null;
        $agent_id = isset($request['agent_id']) ? trim($request['agent_id']) : null;

        if ($agent_type && $agent_id) {
            switch($agent_type){
                case 'at':
                    Yii::$app->redis->del('fc_limit_' . $line_id . '_agent_id_' . $agent_id . 'A');
                    break;
                case 'ua':
                    $ats = AgentModel::getAids('at', ['pid'=>$agent_id]);
                    foreach($ats as $at){
                        Yii::$app->redis->del('fc_limit_' . $line_id . '_agent_id_' . $at['id'] . 'A');
                    }
                    break;
                case 'sh':
                    $uas = AgentModel::getAids('ua', ['pid'=>$agent_id]);
                    foreach($uas as $ua){
                        $ats = AgentModel::getAids('at', ['pid'=>$ua['id']]);
                        foreach($ats as $at){
                            Yii::$app->redis->del('fc_limit_' . $line_id . '_agent_id_' . $at['id'] . 'A');
                        }
                    }
                    break;
            }
        } elseif ($line_id) {
            $ats = AgentModel::getAids('at', ['line_id'=>$line_id]);
            foreach($ats as $at){
                Yii::$app->redis->del('fc_limit_' . $line_id . '_agent_id_' . $at['id'] . 'A');
            }
        }
    }

    public function trans($data) {
        $lotteryOrm = new lotteryOrm();
        foreach ($data as $key => &$val) {
            if ($val['fc_type'] == 'pc_28')
                $val['fc_type'] = 'pc_dd'; // PC蛋蛋
            $getCH = $lotteryOrm->getCH($val['fc_type']);
            if (!empty($getCH)) {// 如果没有翻译数据，则不进行翻译
                $val['gameplayText'] = array_key_exists($val['gameplay'], $getCH) ? $getCH[$val['gameplay']] : $val['gameplay'];
            } else {
                $val['gameplayText'] = $val['gameplay'];
            }
            $ages[] = $val['sort'];
            $ages2[] = $val['id'];
        }unset($val);

        array_multisort($ages, SORT_ASC, $ages2, SORT_ASC, $data);
        return $data;
    }

    public function showData($type = '') {

        switch($type){
            case 'lines':
                $lines = parent::getLines();
                $data = ArrayHelper::index($lines, 'line_id');
                break;
            case 'games':
                $games = parent::getAllFcTypes();
                $data = ArrayHelper::index($games, 'type');
                break;
            case 'agents':
                $agents = AgentModel::getAgent('at');
                $data = ArrayHelper::index($agents, 'id');
                break;
            default:
                $lines = parent::getLines();
                $games = parent::getAllFcTypes();
                $agents = AgentModel::getAgent('at');

                $lines = ArrayHelper::index($lines, 'line_id');
                $games = ArrayHelper::index($games, 'type');
                $agents = ArrayHelper::index($agents, 'id');

                $data['lines'] = $lines;
                $data['games'] = $games;
                $data['agents'] = $agents;
                break;
        }

        return $data;
    }

}
