<?php

namespace app\controllers;

use Yii;
use app\models\Goods;
use app\models\GoodsSearch;
use app\models\OperateLog;
use app\models\SGoods;
use app\models\Shops;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

/**
 * GoodsController implements the CRUD actions for Goods model.
 */
class GoodsController extends BaseController
{
    /**
     * Lists all Goods models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new GoodsSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Goods model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Goods model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Goods();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $model->created = date('Y-m-d H:i:s');
            $file = UploadedFile::getInstance($model,'file');
            if ($file) {
                $model->picture = date('Y-m-d') . '-' .uniqid() . '.' . $file->extension;  
                $file->saveAs('uploads/' . $model->picture);
            }
            if ($model->save()) {
                OperateLog::insertLog(102, 
                    $model->id,
                    Yii::$app->user->id,
                    Yii::$app->request->getUserIP(),
                    OperateLog::OPERATE_TYPE_APPEND,
                    '添加商品：' . $model->name,
                    '总店入库商品'
                    );
                return $this->redirect(['view', 'id' => $model->id]);
            }  
        } else {
            $model->status = 1;
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing Goods model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $file = UploadedFile::getInstance($model,'file');
            if ($file) {
                $model->picture = date('Y-m-d') . '-' .uniqid() . '.' . $file->extension;  
                $file->saveAs('uploads/' . $model->picture);
            }
            if ($model->save()) {
                OperateLog::insertLog(102, 
                    $model->id,
                    Yii::$app->user->id,
                    Yii::$app->request->getUserIP(),
                    OperateLog::OPERATE_TYPE_ALTER,
                    '修改商品：' . $model->name,
                    '修改商品'
                    );
                return $this->redirect(['view', 'id' => $model->id]);
            }
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Goods model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        if (($model = Goods::findOne($id)) !== null) {
            $model->delete();
            OperateLog::insertLog(102, 
                    $model->id,
                    Yii::$app->user->id,
                    Yii::$app->request->getUserIP(),
                    OperateLog::OPERATE_TYPE_ALTER,
                    '删除商品：' . $model->name,
                    '删除商品'
                    );
            return $this->redirect(['index']);
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionAllocation($id)
    {
        if (($model = Goods::findOne($id)) !== null) {
            $shops = Shops::getNumber($id);
            // var_dump($shops);exit;
            return $this->render(allocation, [
                'model' => $model,
                'shops' => $shops, 
            ]);
        }  else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }          
    }

    /**
     * Finds the Goods model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Goods the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Goods::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionExecAllo()
    {
        if ($data = Yii::$app->request->post()) {
            $goods = Goods::findOne($data['fid']);
            $num = 0;
            $log = [];
            foreach ($data['Goods'] as $good) {
                if ($good['sale_num'] == 0) continue;
                $num += $good['sale_num'];
                $exec = SGoods::find()->where(['shop_id' => $good['shop_id'] , 'fid' => $data['fid']])->one();
                if ($exec) {
                    //修改
                    $exec->sale_num += $good['sale_num'];
                    if ($exec->save()) {
                        OperateLog::insertLog(103, 
                        $exec->id,
                        Yii::$app->user->id,
                        Yii::$app->request->getUserIP(),
                        OperateLog::OPERATE_TYPE_APPEND,
                        '入库：' . Goods::getNameFromId($exec->fid),
                        '总部派发数量:' . $good['sale_num'],
                        $good['shop_id']
                        );
                    }
                } else {
                    //新增
                    $exec = new SGoods();
                    $exec->created = date('Y-m-d H:i:s');
                    $exec->fid = $data['fid'];
                    $exec->shop_id = $good['shop_id'];
                    $exec->sale_num = $good['sale_num'];
                    $exec->sale_price = $goods->price;
                    if ($exec->save()) {
                        OperateLog::insertLog(103, 
                        $exec->id,
                        Yii::$app->user->id,
                        Yii::$app->request->getUserIP(),
                        OperateLog::OPERATE_TYPE_APPEND,
                        '新品入库：' . Goods::getNameFromId($exec->fid),
                        '总部派发数量:' . $good['sale_num'],
                        $good['shop_id']
                        );
                    }
                }
                $log[] = Shops::getNameFromId($good['shop_id']) . ':' . $good['sale_num'] . '件';
            }
            $goods->num -= $num;
            if ($goods->save()) {
                OperateLog::insertLog(102, 
                    $goods->id,
                    Yii::$app->user->id,
                    Yii::$app->request->getUserIP(),
                    OperateLog::OPERATE_TYPE_ALTER,
                    '派发商品：' . $goods->name . ' 派发数量:' . $num,
                    implode(',', $log)
                    );
                Yii::$app->session->setFlash('success', '派发成功');
                return $this->redirect('/goods/index');
            }       
        }
    }

    /**
     * 操作日志
     */
    public function actionDolog()
    {
        $query_params = Yii::$app->request->queryParams;
        // list($query_params['start'], $query_params['end']) = explode('--', $query_params['time']);
        $currentPage = $query_params['page'] ? $query_params['page'] : 1;
        $pageSize = $query_params['per-page'] ? $query_params['per-page'] : 5;

        $dologs = OperateLog::find()
            ->innerJoinWith('user')
            ->where(['module' => 102])
            ->orderBy('created DESC')
            ->searchDoUser($query_params['doUser'])
            ->searchIp($query_params['ip'])
            // ->searchType($query_params['type'])
            ->searchLog($query_params['log'])
            ->searchReason($query_params['reason']);
            // ->searchAll($query_params['search']);

        $pageInfo = $this->_page($dologs, $currentPage, $pageSize);

        return $this->render('../auth/dolog.twig', [
            'dologs' => $pageInfo['data'],
            'pages' => $pageInfo['pages'],
        ]);
    }
}
