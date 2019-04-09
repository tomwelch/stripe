<?php
/**
 * Stripe Payments plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2018 Enupal LLC
 */

namespace enupal\stripe\elements;

use Craft;
use craft\base\Element;
use craft\behaviors\FieldLayoutBehavior;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;
use enupal\stripe\models\Settings;
use enupal\stripe\enums\DiscountType;
use enupal\stripe\enums\SubscriptionType;
use enupal\stripe\Stripe;
use enupal\stripe\validators\DiscountValidator;
use craft\helpers\UrlHelper;
use craft\elements\actions\Delete;

use enupal\stripe\elements\db\PaymentFormsQuery;
use enupal\stripe\records\PaymentForm as PaymentFormRecord;
use enupal\stripe\Stripe as StripePlugin;
use craft\validators\UniqueValidator;

/**
 * PaymentForm represents a entry element.
 *
 * @property $enupalMultiplePlans
 * @property $singlePlanInfo
 */
class PaymentForm extends Element
{
    /**
     * @inheritdoc
     */
    public $id;

    /**
     * @var string Name.
     */
    public $name;

    public $companyName;

    /**
     * @var string Sku
     */
    public $handle;

    /**
     * @var bool
     */
    public $enableCheckout = 1;

    /**
     * @var string Payment Type
     */
    public $paymentType;

    /**
     * @var string Currency
     */
    public $currency;

    /**
     * @var string Language
     */
    public $language;

    /**
     * @var int Amount
     */
    public $amount;

    /**
     * @inheritdoc
     */
    public $enabled;

    public $quantity;
    public $hasUnlimitedStock;
    public $customerQuantity;
    public $soldOutMessage;
    public $discountType;
    public $discount;
    public $shippingAmount;
    public $itemWeight;
    public $itemWeightUnit;
    public $priceMenuName;
    public $priceMenuOptions;

    public $showItemName;
    public $showItemPrice;
    public $showItemCurrency;
    // Button
    public $buttonText;
    public $paymentButtonProcessingText;
    public $checkoutButtonText;
    public $returnUrl;
    // Subscriptions
    public $enableSubscriptions;
    public $subscriptionType;
    public $singlePlanSetupFee;
    public $singlePlanInfo;
    public $enableCustomPlanAmount;
    public $customPlanMinimumAmount;
    public $customPlanDefaultAmount;
    public $customPlanInterval;
    public $customPlanFrequency;
    public $subscriptionStyle;
    public $selectPlanLabel;
    public $singlePlanTrialPeriod;

    public $amountType;
    public $minimumAmount;
    public $customAmountLabel;
    public $verifyZip;
    public $enableBillingAddress;
    public $enableShippingAddress;
    public $logoImage;
    public $enableRememberMe;

    public $enableRecurringPayment;
    public $recurringPaymentType;

    public $buttonClass;
    public $enableTemplateOverrides;
    public $templateOverridesFolder;

    protected $env;
    protected $ipnUrl;
    protected $publishableKey;

    /**
     * @var Settings
    */
    protected $settings;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'fieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => self::class
            ],
        ]);
    }

    public function init()
    {
        parent::init();

        if (!$this->settings){
            $this->settings = StripePlugin::$app->settings->getSettings();
        }

        $this->returnUrl = $this->returnUrl ? $this->returnUrl : $this->settings->returnUrl;
        $this->currency = $this->currency ? $this->currency : $this->settings->defaultCurrency;
    }

    /**
     * @param Order|null $order
     * @return string
     * @throws \Throwable
     * @throws \yii\base\Exception
     */
    public function getReturnUrl(Order $order = null)
    {
        // by default return to the same page
        $returnUrl = '';

        if ($this->returnUrl){
            $returnUrl = $this->getSiteUrl($this->returnUrl);
        }

        if ($order){
            $returnUrl = Craft::$app->getView()->renderObjectTemplate($returnUrl, $order);
        }

        return $returnUrl;
    }

    /**
     * @return string
     */
    public function getPublishableKey()
    {
        $this->publishableKey = StripePlugin::$app->settings->getPublishableKey();

        return $this->publishableKey;
    }

    /**
     * @return string
     */
    public function getTax()
    {
        $tax = $this->settings->tax ?? null;

        return $tax;
    }

    /**
     * @return string
     */
    public function getTaxType()
    {
        $taxType = null;

        switch ($this->settings->taxType) {
            case DiscountType::RATE:
                {
                    $taxType = 'tax_rate';
                    break;
                }
            case DiscountType::AMOUNT:
                {
                    $taxType = 'rate';
                    break;
                }
        }

        return $taxType;
    }

    /**
     * @return string
     */
    public function getDiscount()
    {
        $discount = $this->discount ?? null;

        return $discount;
    }

    /**
     * @return string
     */
    public function getDiscountType()
    {
        $discountType = null;

        switch ($this->discountType) {
            case DiscountType::RATE:
                {
                    $discountType = 'discount_rate';
                    break;
                }
            case DiscountType::AMOUNT:
                {
                    $discountType = 'discount_amount';
                    break;
                }
        }

        return $discountType;
    }

    /**
     * Returns the field context this element's content uses.
     *
     * @access protected
     * @return string
     */
    public function getFieldContext(): string
    {
        return 'enupalStripeForm:'.$this->id;
    }

    /**
     * Returns the element type name.
     *
     * @return string
     */
    public static function displayName(): string
    {
        return StripePlugin::t('Stripe PaymentForms');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle()
    {
        return 'stripe-payment-forms';
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        $behaviors = $this->getBehaviors();
        $fieldLayout = $behaviors['fieldLayout'];

        return $fieldLayout->getFieldLayout();
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl(
            'enupal-stripe/forms/edit/'.$this->id
        );
    }

    /**
     * Use the name as the string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     *
     * @return PaymentFormsQuery The newly created [[PaymentFormsQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new PaymentFormsQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => StripePlugin::t('All Payment Forms'),
            ]
        ];

        // @todo add groups

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        // Delete
        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Delete::class,
            'confirmationMessage' => StripePlugin::t("Are you sure you want to delete this Payment Form, and all of it's orders?"),
            'successMessage' => StripePlugin::t('Payment Forms deleted.'),
        ]);

        return $actions;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['name', 'handle'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        $attributes = [
            'elements.dateCreated' => StripePlugin::t('Date Created'),
            'name' => StripePlugin::t('Name'),
            'handle' => StripePlugin::t('Handle')
        ];

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        $attributes = [];
        $attributes['name'] = ['label' => StripePlugin::t('Name')];
        $attributes['handle'] = ['label' => StripePlugin::t('Handle')];
        $attributes['amount'] = ['label' => StripePlugin::t('Amount')];
        $attributes['dateCreated'] = ['label' => StripePlugin::t('Date Created')];

        return $attributes;
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = ['name', 'amount', 'handle', 'dateCreated'];

        return $attributes;
    }

    /**
     * @inheritdoc
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\base\InvalidConfigException
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'amount':
                {
                    if ($this->amount) {
                        return $this->getAmountAsCurrency();
                    }

                    return Craft::$app->getFormatter()->asCurrency($this->$attribute * -1, $this->currency);
                }
            case 'dateCreated':
                {
                    return $this->dateCreated->/** @scrutinizer ignore-call */ format("Y-m-d H:i");
                }
        }

        return parent::tableAttributeHtml($attribute);
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'dateCreated';
        return $attributes;
    }

    /**
     * @inheritdoc
     * @param bool $isNew
     * @throws \Exception
     */
    public function afterSave(bool $isNew)
    {
        $record = new PaymentFormRecord();
        // Get the PaymentForm record
        if (!$isNew) {
            $record = PaymentFormRecord::findOne($this->id);

            if (!$record) {
                throw new \Exception('Invalid PaymentForm ID: '.$this->id);
            }
        } else {
            $record->id = $this->id;
        }

        $record->name = $this->name;
        $record->companyName = $this->companyName;

        $record->handle = $this->handle;
        $record->enableCheckout = $this->enableCheckout;
        $record->paymentType = $this->paymentType;
        $record->currency = $this->currency;
        $record->language = $this->language;
        $record->amountType = $this->amountType;
        $record->amount = (float)$this->amount;
        $record->minimumAmount = (float)$this->minimumAmount;
        $record->customAmountLabel = $this->customAmountLabel;
        $record->logoImage = $this->logoImage;
        $record->enableRememberMe = $this->enableRememberMe;
        $record->quantity = $this->quantity;
        $record->hasUnlimitedStock = $this->hasUnlimitedStock;
        $record->discountType = $this->discountType;
        $record->discount = $this->discount;

        $record->verifyZip = $this->verifyZip;
        $record->enableBillingAddress = $this->enableBillingAddress;
        $record->enableShippingAddress = $this->enableShippingAddress;
        $record->customerQuantity = $this->customerQuantity ? $this->customerQuantity : 0;

        $record->enableSubscriptions = $this->enableSubscriptions;
        $record->subscriptionType = $this->subscriptionType;

        if ($this->enableSubscriptions){
            if ($this->subscriptionType == SubscriptionType::SINGLE_PLAN){
                $record->singlePlanSetupFee = $this->singlePlanSetupFee;
                $record->singlePlanInfo = $this->singlePlanInfo;
                $record->enableCustomPlanAmount = $this->enableCustomPlanAmount;
                $record->customPlanMinimumAmount = $this->customPlanMinimumAmount;
                $record->customPlanDefaultAmount = $this->customPlanDefaultAmount;
                $record->customPlanInterval = $this->customPlanInterval;
                $record->customPlanFrequency = $this->customPlanFrequency;
            }
        }
        $record->subscriptionStyle = $this->subscriptionStyle;
        $record->selectPlanLabel = $this->selectPlanLabel;
        $record->singlePlanTrialPeriod = $this->singlePlanTrialPeriod;

        $record->enableRecurringPayment = $this->enableRecurringPayment;
        $record->recurringPaymentType = $this->recurringPaymentType;

        $record->buttonClass = $this->buttonClass;
        $record->checkoutButtonText = $this->checkoutButtonText;

        $record->returnUrl = $this->returnUrl;
        $record->buttonText = $this->buttonText;
        $record->paymentButtonProcessingText = $this->paymentButtonProcessingText;

        $record->enableTemplateOverrides = $this->enableTemplateOverrides;
        $record->templateOverridesFolder = $this->templateOverridesFolder;

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'handle'], 'required'],
            [['paymentType'], 'required', 'when' => function($model) {
                return $model->enableCheckout != 1;
            }],
            [['name', 'handle'], 'string', 'max' => 255],
            [['name', 'handle'], UniqueValidator::class, 'targetClass' => PaymentFormRecord::class],
            [
                ['discount'],
                DiscountValidator::class
            ],
        ];
    }

    /**
     * @param $url
     *
     * @return string
     * @throws \yii\base\Exception
     */
    private function getSiteUrl($url)
    {
        if (UrlHelper::isAbsoluteUrl($url)){
            return $url;
        }

        return UrlHelper::siteUrl($url);
    }

    /**
     * Returns the Stripe Payment Form in template
     *
     * @param array|null $options
     *
     * @return string
     * @throws \yii\base\Exception
     */
    public function paymentForm(array $options = null)
    {
        return StripePlugin::$app->paymentForms->getPaymentFormHtml($this->handle, $options);
    }

    /**
     * @param $options array
     * @return string
     * @throws \yii\web\ServerErrorHttpException
     * @throws \Exception
     */
    public function getPublicData($options = null)
    {
        $info = Craft::$app->getInfo();
        $logoUrl = null;
        $logoAsset = $this->getLogoAsset();
        $calculateFinalAmount = $options['calculateFinalAmount'] ?? true;

        if ($logoAsset){
            $logoUrl = $logoAsset->getUrl();
        }

        $quantity = (int)($options['quantity'] ?? 1);

        $amount = $options['amount'] ?? $this->amount;
        if ($calculateFinalAmount){
            $amount = $amount * $quantity;
        }
        $currency = $this->currency ?? 'USD';
        $multiplePlansAmounts = [];
        $setupFees = [];

        $description = $options['description'] ?? $this->name;
        $logoUrl = $options['logo'] ?? $logoUrl;
        $currency = $options['currency'] ?? $currency;

        if ($this->enableSubscriptions){
            if ($this->subscriptionType == SubscriptionType::SINGLE_PLAN){
                if (!$this->enableCustomPlanAmount){
                    $plan = Json::decode($this->singlePlanInfo, true);
                    $currency = strtoupper($plan['currency']);
                    $amount = StripePlugin::$app->plans->getPlanAmount($plan, $quantity);
                }
            }else{
                // Multiple plans
                foreach ($this->enupalMultiplePlans as $item) {
                    if ($item->selectPlan->value){
                        $plan = StripePlugin::$app->plans->getStripePlan($item->selectPlan->value);
                        if ($plan){
                            $multiplePlansAmounts[$plan->id]['amount'] = StripePlugin::$app->plans->getPlanAmount($plan, $quantity);
                            $multiplePlansAmounts[$plan->id]['currency'] = $plan['currency'];
                            $setupFee = StripePlugin::$app->orders->getSetupFeeFromMatrix($plan->id, $this);
                            $setupFees[$plan->id] = $setupFee;
                        }
                    }
                }
            }
        }

        $currentUser = Craft::$app->getUser()->getIdentity();
        $email = '';

        if ($this->settings->currentUserEmail){
            $email = $currentUser->email ?? '';
        }

        $applyTax = false;
        // Tax logic - apply just to subscriptions
        if ($this->enableSubscriptions){
            $applyTax = true;
        }

        $paymentTypeIds = json_decode($this->paymentType, true);

        $publicData = [
            'paymentFormId' => $this->id,
            'handle' => $this->handle,
            'amountType' => $this->amountType,
            'customerQuantity' => $this->customerQuantity ? (boolean)$this->customerQuantity : false,
            'buttonText' => $this->buttonText,
            'paymentButtonProcessingText' => $this->paymentButtonProcessingText ? $this->getPaymentFormText($this->paymentButtonProcessingText) : $this->getPaymentFormText(),
            'pbk' => $this->getPublishableKey(),
            'testMode' => $this->settings->testMode,
            'enableRecurringPayment' => (boolean)$this->enableRecurringPayment,
            'recurringPaymentType' => $this->recurringPaymentType,
            'customAmountLabel' => Craft::$app->view->renderString($this->customAmountLabel ?? '' , ['button' => $this]),
            // subscriptions
            'enableSubscriptions' => $this->enableSubscriptions,
            'subscriptionType' => $this->subscriptionType,
            'subscriptionStyle' => $this->subscriptionStyle,
            'singleSetupFee' => $this->singlePlanSetupFee,
            'enableCustomPlanAmount' => $this->enableCustomPlanAmount,
            'multiplePlansAmounts' => $multiplePlansAmounts,
            'setupFees' => $setupFees,
            'applyTax' => $applyTax,
            'enableTaxes' => $this->settings->enableTaxes,
            'tax' => $this->settings->tax,
            'currencySymbol' => $this->getCurrencySymbol(),
            'taxLabel' => Craft::t('site', 'Tax Amount'),
            'paymentTypeIds' => $paymentTypeIds,
            'enableShippingAddress' => $this->enableShippingAddress,
            'enableBillingAddress' => $this->enableBillingAddress,
            'stripe' => [
                'description' => $description,
                'panelLabel' =>  $this->checkoutButtonText ?? 'Pay {{amount}}',
                'name' => $this->companyName ?? $info->name,
                'currency' => $currency,
                'locale' => $this->language,
                'amount' => $amount,
                'image' => $logoUrl,
                'email' => $email,
                'allowRememberMe' => (boolean)$this->enableRememberMe,
                'zipCode' => (boolean)$this->verifyZip,
            ]
        ];

        // Booleans
        if ($this->enableShippingAddress){
            // 'billingAddress' must be enabled whenever 'shippingAddress' is.
            $publicData['stripe']['shippingAddress'] = true;
            $publicData['stripe']['billingAddress'] = true;
        }

        if ($this->enableBillingAddress){
            $publicData['stripe']['billingAddress'] = true;
        }

        return json_encode($publicData);
    }

    /**
     * @return \craft\base\ElementInterface|null
     */
    public function getLogoAsset()
    {
        $logoElement = null;

        if ($this->logoImage) {
            $logo = $this->logoImage;
            if (is_string($logo)) {
                $logo = json_decode($this->logoImage);
            }

            if (count($logo)) {
                $logoElement = Craft::$app->elements->getElementById($logo[0]);
            }
        }

        return $logoElement;
    }

    /**
     * @param string $default
     *
     * @return string
     */
    public function getPaymentFormText($default = null)
    {
        if (is_null($default)){
            $default = "Pay with card";
        }

        $buttonText = Craft::t('site', $default);

        if ($this->buttonText){
            $buttonText =  Craft::$app->view->renderString($this->buttonText, ['button' => $this]);
        }

        return $buttonText;
    }

    /**
     * @param string $default
     *
     * @return string
     */
    public function getCustomLabel($default = "Pay what you want:")
    {
        $text = Craft::t('site', $default);

        if ($this->customAmountLabel){
            $text = $this->customAmountLabel;
        }

        return $text;
    }


    /**
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public function getCurrencySymbol()
    {
        $pattern = Craft::$app->formatter->asCurrency(123, $this->currency);

        // the spacings between currency symbol and number are ignored, because
        // a single space leads to better readability in combination with input
        // fields
        // the regex also considers non-break spaces (0xC2 or 0xA0 in UTF-8)
        preg_match('/^([^\s\xc2\xa0]*)[\s\xc2\xa0]*123([,.]0+)?[\s\xc2\xa0]*([^\s\xc2\xa0]*)$/u', $pattern, $matches);

        $symbol = '';

        if (!empty($matches[1])) {
            $symbol = $matches[1];
        } elseif (!empty($matches[3])) {
            $symbol = $matches[3];
        }

        return $symbol;
    }

    /**
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
    public function getAmountAsCurrency()
    {
        return Craft::$app->getFormatter()->asCurrency($this->amount, $this->currency);
    }

    /**
     * @return mixed
     */
    public function getFormFieldHandle()
    {
        return Stripe::$app->paymentForms::BASIC_FORM_FIELDS_HANDLE;
    }

    /**
     * @return mixed
     */
    public function getMultiplePlansHandle()
    {
        return Stripe::$app->paymentForms::MULTIPLE_PLANS_HANDLE;
    }

    /**
     * @return array|mixed
     * @throws \yii\base\InvalidConfigException
     */
    public function getSinglePlan()
    {
        $singlePlan = [];

        if ($this->singlePlanInfo){
            $singlePlan = Json::decode($this->singlePlanInfo, true);
            $singlePlan['defaultPlanName'] = Stripe::$app->plans->getDefaultPlanName($singlePlan);
        }

        return $singlePlan;
    }

    /**
     * @return string
     */
    public function getDefaultPaymentMethod()
    {
        $methods = json_decode($this->paymentType, true);

        return $methods[0] ?? '';
    }
}
