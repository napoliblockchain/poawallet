<?php
namespace app\models;

use Yii;
use yii\base\Model;
use app\components\Throttle;

/** Account credentials only; never reads or modifies wallet keys, seeds or balances. */
final class ChangePasswordForm extends Model
{
    public $current_password;
    public $password;
    public $repeat_password;
    public int $retryAfter = 0;

    public function rules(): array
    {
        return [
            [['current_password', 'password', 'repeat_password'], 'filter', 'filter' => static fn($v) => is_string($v) ? $v : ''],
            [['current_password', 'password', 'repeat_password'], 'required'],
            ['current_password', 'string', 'max' => 4096],
            ['password', 'string', 'min' => Yii::$app->params['user.passwordMinLength'], 'max' => 72],
            ['repeat_password', 'string', 'max' => 72],
            ['password', function ($attribute): void {
                if (is_string($this->$attribute) && strlen($this->$attribute) > 72) $this->addError($attribute, Yii::t('app', 'Password must not exceed 72 bytes.'));
            }],
            ['repeat_password', 'compare', 'compareAttribute' => 'password', 'message' => Yii::t('app', 'Passwords do not match.')],
        ];
    }

    public function attributeLabels(): array
    {
        return ['current_password' => Yii::t('app', 'Current password'), 'password' => Yii::t('app', 'New password'), 'repeat_password' => Yii::t('app', 'Confirm password')];
    }

    public function change(): bool
    {
        if (Yii::$app->user->isGuest || !$this->validate()) return false;
        $id = (int) Yii::$app->user->id;
        $key = 'password-change-user-' . $id;
        $this->retryAfter = max(Throttle::hit($key, [5, 900]), Throttle::hit('password-change-ip-' . Yii::$app->request->userIP, [20, 900]));
        if ($this->retryAfter > 0) return $this->refuse();
        $db = Yii::$app->db; $tx = $db->beginTransaction();
        try {
            $user = Users::findBySql('SELECT * FROM {{%mp_users}} WHERE id=:id FOR UPDATE', [':id' => $id])->one();
            if ($user === null || (int) $user->status_activation_code !== Users::STATUS_ACTIVE
                || !hash_equals((string) Yii::$app->user->identity->authKey, (string) $user->authKey)
                || !Yii::$app->security->validatePassword($this->current_password, $user->password)) {
                $tx->rollBack(); return $this->refuse();
            }
            $previous = $user->password;
            $user->setPassword($this->password); $user->generateAuthKey();
            $user->accessToken = Yii::$app->security->generateRandomString(60);
            if (Users::updateAll(['password' => $user->password, 'authKey' => $user->authKey, 'accessToken' => $user->accessToken, 'activation_code' => null],
                ['id' => $id, 'password' => $previous, 'status_activation_code' => Users::STATUS_ACTIVE]) !== 1) throw new \RuntimeException('password_change_failed');
            Logs::write('Account password changed: user ID ' . $id . '.');
            $tx->commit();
        } catch (\Throwable $error) {
            if ($tx->isActive) $tx->rollBack();
            Yii::warning('Password change failed.', __METHOD__); return $this->refuse();
        }
        Throttle::reset($key);
        try {
            $sent = Yii::$app->mailer->compose(['html' => 'passwordChanged', 'text' => 'passwordChanged-text'])
                ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName']])
                ->setTo($user->email)->setSubject(Yii::t('app', 'Your password has changed'))->send();
            if (!$sent) Yii::warning('Password change notification could not be delivered.', __METHOD__);
        } catch (\Throwable $error) { Yii::warning('Password change notification could not be delivered.', __METHOD__); }
        return true;
    }

    private function refuse(): bool
    {
        $this->addError('current_password', Yii::t('app', 'Unable to change password. Check your entries or try again later.'));
        return false;
    }
}
