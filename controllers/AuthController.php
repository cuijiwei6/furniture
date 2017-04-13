<?php 
	
namespace app\controllers;

use Yii;
use app\models\Role;
use app\models\User;
use app\components\Salt;
use app\models\OperateLog;

class AuthController extends BaseController
{

	/**
	 * 管理员列表
	 */
	public function actionIndex()
	{
		$user = new user();	

		//添加管理员
		if ($post_data = Yii::$app->request->post()) {
			$data = $post_data['AdminUsers'];
			$info = Salt::generateSalt($data['password']);

			//赋值
			$user->username = $data['username'];
			$user->role = $data['role'];
			$user->real_name = $data['real_name'];
			$user->status = User::USER_TABLE_STATUS_ACTIVE;
            $user->created = date('Y-m-d H:i:s');
            $user->password = $info['hash'];
            $user->salt = $info['salt'];

            if ($user->save()){
            	//记录日志
            	OperateLog::insertLog(101, 
            		$user->id,
            		Yii::$app->user->id,
            		Yii::$app->request->getUserIP(),
            		OperateLog::OPERATE_TYPE_APPEND,
            		'添加管理员' . $user->real_name,
            		'添加管理员'
            		);
            	Yii::$app->session->setFlash('success', '新建管理员成功');
            	return $this->redirect(['/auth/index']);
            }
			Yii::$app->session->setFlash('error', '新建管理员失败');
			return $this->redirect(['/auth/index']);
		}

		//自定义查询
		$query_params = Yii::$app->request->query_params;
		$currentPage = $query_params['page'] ? $query_params['page'] : 1;
		$pageSize = $query_params['per-page'] ? $query_params['per-page'] : 10;
		$users = $user::find()->where(['<>', 'status', 3])->all();
		return $this->render('index', ['users' => $users]);
	}

	/**
	 *管理员编辑
	 */
	public function actionAdmin_change_role()
	{
		if ($post_data = Yii::$app->request->post()) {
			$user = User::findOne($post_data['id']);
			$user->role = $post_data['role'];
			if ($user->save()) {
				OperateLog::insertLog(101, 
            		$user->id,
            		Yii::$app->user->id,
            		Yii::$app->request->getUserIP(),
            		OperateLog::OPERATE_TYPE_ALTER,
            		'将' . $user->real_name . '设置为' . $user->role0->name,
            		'编辑管理员'
            		);
				Yii::$app->session->setFlash('success', '编辑管理员成功');
				return $this->redirect(['/auth/index']);
			} 
		}
		Yii::$app->session->setFlash('error', '修改失败');
		return $this->redirect(['/auth/index']);
	}

	/**
	 * 修改管理员状态 ajax调用
	 */
	public function actionAdmin_change_status()
	{
		if ($data = Yii::$app->request->post()) {
			$do_id = Yii::$app->user->id;
            $ip	= Yii::$app->request->getUserIP();

            $user = User::findOne($data['id']);
            switch ($data['eventType']) {
                case '3':
                    $user->status = 3;
                    $user->update();
                    OperateLog::insertLog('101',
                        $user->id, $do_id, $ip,
                        3, "删除管理员 $user->real_name", $data['reason']);
                    Yii::$app->session->setFlash('success', '管理员已删除');
                    break;
                case '2':
                    $user->status = 1;
                    $user->update();
                    OperateLog::insertLog('101',
                        $user->id, $do_id, $ip,
                        4, "启用管理员 $user->real_name", $data['reason']);
                   	Yii::$app->session->setFlash('success', '管理员已启用');
                    break;
                case '1':
                    $user->status = 2;
                    $user->update();
                    OperateLog::insertLog('101',
                        $user->id, $do_id, $ip,
                        5, "停用管理员 $user->real_name", $data['reason']);
                  	Yii::$app->session->setFlash('success', '管理员已停用');
                    break;
            }
		}
	}

	/**
	 *修改密码 ajax调用 
	 */
	public function actionAdmin_change_password()
	{
		if ($data = Yii::$app->request->post()) {
            $id = $data['id'];
            $passwd = $data['pwd'];
            $password_confirm = $data['confirmPwd'];
            if ($passwd != $password_confirm) {
                Yii::$app->session->setFlash('error', '密码不一致');
                die;
            } 

            $salt_hash = Salt::generateSalt($passwd);
            $user = User::findOne($id);
            $user->password = $salt_hash['hash'];
            $user->salt = $salt_hash['salt'];
            $user->save();
            $do_id = Yii::$app->User->id;
            $ip = Yii::$app->request->getUserIP();
            OperateLog::insertLog('101',
                $user->id, $do_id, $ip,
                3, "$user->real_name 修改密码", '修改密码');
            Yii::$app->session->setFlash('success', '密码已更新');
        }
	}
	


	/**
	 * 管理员组列表
	 */
	public function actionRole()
	{
		$role = new role();
		if ($post_data = Yii::$app->request->post()) {
			if ($role->load($post_data)) {
				$role->created = $role->updated = date('Y-m-d H:i:s');
				if ($role->save()) {
					Yii::$app->session->setFlash('success', '管理员组新建成功');
					return $this->redirect(['/auth/role']);
				}
			}
		}
		$data = $role::find()->asArray()->all();
		return $this->render('role', ['data' => $data]);
	}

	/**
	 * 管理员组编辑
	 */
	public function actionUpdate_role()
	{
		if ($data = Yii::$app->request->post()) {
			$role = Role::findOne($data['id']);
			$role->name = $data['name'];
			$role->updated = date('Y-m-d H:i:s');
			if ($role->save()) {
				Yii::$app->session->setFlash('success', '修改成功');
				return $this->redirect(['/auth/role']);
			}
		}
		Yii::$app->session->setFlash('error', '非法操作');
		return $this->redirect(['/auth/role']);
	}

	/**
	 * 管理员组删除
	 */
	public function actionDelete_role()
	{

	}
} 
