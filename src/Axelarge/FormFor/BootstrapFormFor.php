<?php
namespace Axelarge\FormFor;

use Axelarge\HtmlHelpers\Html;

/**
 * @method string _text(string $name, array $attributes = array())
 * @method string _textArea(string $name, array $attributes = array())
 * @method string _checkBox(string $name, array $attributes = array())
 * @method string _radio(string $name, $value, $attributes = array())
 * @method string _password(string $name, array $attributes = array())
 * @method string _select(string $name, array $collection, array $attributes = array())
 */
class BootstrapFormFor
{
    protected $formFor;
    protected $inputClass;
    protected $model;
    /** @var array Options array passed to constructor. Used when creating fieldsFor() */
    protected $optionsForNested;

    /** @var \callable */
    protected $errorGetter;


    public function __construct(FormFor $formFor, array $options = array())
    {
        $this->formFor = $formFor;
        $this->optionsForNested = $options;
        $this->inputClass = $this->arrDelete($options, 'input-class');
        $this->model = $formFor->getModel();

        $this->setDefaultErrorGetter();
    }

    /**
     * Creates an instance with a default FormFor
     *
     * @static
     * @param mixed $model
     * @param array $options
     * @return BootstrapFormFor
     */
    public static function forge($model, array $options = array())
    {
        $formFor = new FormFor($model);
        return new static($formFor, $options);
    }

    /**
     * Sets the error getter
     */
    protected function setDefaultErrorGetter()
    {
        if ($this->model instanceof \Fuel\Core\Model_Crud) {
            $validation = $this->model->validation();
            $this->errorGetter = function($fieldName) use ($validation)
            {
                /** @var $validation \Fuel\Core\Validation */
                return $validation->error($fieldName);
            };
        }
    }

    /**
     * Returns the underlying model
     *
     * @return object
     */
    public function getModel()
    {
        return $this->formFor->getModel();
    }

    /**
     * Form open tag
     *
     * @param array $extraOptions Extra attributes for FormFor open()
     * @return string
     */
    public function open($extraOptions = array())
    {
        $extraOptions = array_merge(array('class' => 'form-horizontal'), $extraOptions);
        return $this->formFor->open($extraOptions);
    }

    /**
     * Form closing tag
     *
     * @return string
     */
    public function close()
    {
        return $this->formFor->close();
    }

    /**
     * Creates a label
     *
     * @param string $name
     * @param string $text
     * @param array $options
     * @return string
     */
    public function label($name, $text = null, array $options = array())
    {
        $options = array_merge(array('class' => 'control-label'), $options);
        return $this->formFor->label($name, $text, $options);
    }

    /**
     * Creates a control group with a text input
     *
     * @param string $name
     * @param array $attributes
     * @param array $rowAttributes
     * @return string
     */
    public function text($name, array $attributes = array(), array $rowAttributes = array())
    {
        $attributes = array_merge(array('class' => $this->inputClass), $attributes);
        return $this->_row($name, $this->formFor->text($name, $attributes), $rowAttributes);
    }

    /**
     * Creates a hidden input field
     *
     * @param string $name Name of the attribute
     * @param array $attributes HTML attributes
     * @return string
     */
    public function hidden($name, array $attributes = array())
    {
        return $this->formFor->hidden($name, $attributes);
    }

    /**
     * Creates a control group with a password input
     *
     * @param string $name
     * @param array $attributes
     * @param array $rowAttributes
     * @return string
     */
    public function password($name, array $attributes = array(), array $rowAttributes = array())
    {
        $attributes = array_merge(array('class' => $this->inputClass), $attributes);
        return $this->_row($name, $this->formFor->password($name, $attributes), $rowAttributes);
    }

    /**
     * Creates a control group with a textarea input
     *
     * @param string $name
     * @param array $attributes
     * @param array $rowAttributes
     * @return string
     */
    public function textArea($name, array $attributes = array(), array $rowAttributes = array())
    {
        $attributes = array_merge(array('class' => $this->inputClass), $attributes);
        return $this->_row($name, $this->formFor->textArea($name, $attributes), $rowAttributes);
    }

    /**
     * Creates a control group with a check box
     *
     * Note: Check boxes do not get the default input class
     *
     * @param string $name
     * @param array $attributes
     * @param array $rowAttributes
     * @return string
     */
    public function checkBox($name, array $attributes = array(), array $rowAttributes = array())
    {
        /*
         * The hidden field has to be prepended to the controls div  so it doesn't break bootstrap's
         * margin-top for the checkbox or rounded corners in case the controls-group is a first-child,
         * so this row has to be constructed manually.
         */
        list($hidden, $checkbox) = $this->formFor->checkBox($name, $attributes, 'array');

        $help = $this->arrDelete($rowAttributes, 'help', '&nbsp;'); // Inline help has to be displayed inside the label

        $content = Html::tag('label', array('class' => 'checkbox'), $checkbox . $help, false);

        $label = $this->label($name, $this->arrDelete($rowAttributes, 'label'));
        $rowAttributes['errors'] = $this->errorTextFor($name);

        return $this->row($label . $hidden, $content, $rowAttributes);
    }

    /**
     * Creates multiple checkboxes for a has-many association
     *
     * @param string $name
     * @param array $collection Associative array of options
     * @param array $attributes
     * @return string
     */
    public function collectionCheckBoxes($name, array $collection, array $attributes = array())
    {
        //TODO: What to do with attributes?
        return $this->_row(
            $name,
            $this->formFor->collectionCheckBoxes($name, $collection, array('class' => 'checkbox inline'), false),
            array()
        );
    }

    /**
     * Creates multiple radio buttons with labels
     *
     * @param string $name
     * @param array $collection Associative array of options
     * @param array $rowAttributes
     * @return string
     */
    public function collectionRadios($name, array $collection, array $rowAttributes = array())
    {
        return $this->row(
            $this->label($name, null, array('for' => false, 'id' => false)),
            $this->formFor->collectionRadios($name, $collection, array('class' => 'radio'), false),
            $rowAttributes
        );
    }

    /**
     * Creates a control group with a select tag
     *
     * @param string $name
     * @param array $collection Associative array of select options
     * @param array $attributes
     * @param array $rowAttributes
     * @return string
     */
    public function select($name, array $collection, array $attributes = array(), array $rowAttributes = array())
    {
        $attributes = array_merge(array('class' => $this->inputClass), $attributes);
        return $this->_row($name, $this->formFor->select($name, $collection, $attributes), $rowAttributes);
    }

    /**
     * Creates a group of buttons featuring radio button functionality
     *
     * @param string $name
     * @param array $collection Associative array of selection options
     * @param array $rowAttributes
     * @return string
     */
    public function buttonGroup($name, array $collection, array $rowAttributes = array())
    {
        $selectedValue = $this->formFor->getValue($name);
        $content = '';
        foreach ($collection as $value => $text) {
            $btnAttributes = array(
                'data-value' => $value,
                'class'      => 'btn btn-large' . ($value === $selectedValue ? ' active' : ''),
            );
            $content .= $this->formFor->button($name, $text, $btnAttributes);
        }

        $divAttributes = array(
            'class'       => 'btn-group masked-radio',
            'data-toggle' => 'buttons-radio',
            'data-field'  => $name,
        );

        return $this->_row($name, Html::tag('div', $divAttributes, $content, false), $rowAttributes);
    }

    /**
     * Create a nested form for a relation
     *
     * @see FormFor::fieldsFor()
     *
     * @param string $name
     * @param mixed|array $modelOrModels
     * @param array $bootstrapOptions
     * @param array $formForOptions
     * @return BootstrapFormFor|BootstrapFormFor[]
     */
    public function fieldsFor($name, $modelOrModels = null, array $bootstrapOptions = array(), array $formForOptions = array())
    {
        $optionsForNested = array_merge($this->optionsForNested, $bootstrapOptions);

        $nestedForm = $this->formFor->fieldsFor($name, $modelOrModels, $formForOptions);
        if (is_array($nestedForm)) {
            foreach ($nestedForm as $idx => $subForm) {
                $nestedForm[$idx] = new BootstrapFormFor($subForm, $optionsForNested);
            }
        } else {
            $nestedForm = new BootstrapFormFor($nestedForm, $optionsForNested);
        }

        return $nestedForm;
    }

    /**
     * Returns the underlying FormFor instance
     *
     * @return FormFor
     */
    public function form()
    {
        return $this->formFor;
    }

    /**
     * Allows calling underlying formFor methods by prefixing them with an underscore
     *
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call($method, $args)
    {
        if ($method[0] === '_') {
            $actualMethod = substr($method, 1);
            if (method_exists($this->formFor, $actualMethod)) {
                return call_user_func_array(array($this->formFor, $actualMethod), $args);
            }
        }

        throw new \BadMethodCallException("Method $method does not exist in " . __CLASS__ . " or the underlying " . get_class($this->formFor) . " object!");
    }

    /**
     * Creates row with label and errors
     *
     * @param string $name
     * @param string $controls
     * @param array $options
     * @return string
     */
    protected function _row($name, $controls, array $options)
    {
        $label = $this->label($name, $this->arrDelete($options, 'label'));
        $options['errors'] = $this->errorTextFor($name);

        return $this->row($label, $controls, $options);
    }

    /**
     * Creates a control-group row
     *
     * @param string $label Label HTML content
     * @param string $controls
     * @param array $options
     * @return string
     */
    public function row($label, $controls, array $options = array())
    {
        $class = $this->arrDelete($options, 'class');
        if (is_array($class)) {
            $class = implode(' ', $class);
        }

        // Add append and prepend fragments
        $controls = $this->prependAndAppend(
            $controls,
            $this->arrDelete($options, 'prepend'),
            $this->arrDelete($options, 'append')
        );

        // Add extra HTML
        if ($extraHtml = $this->arrDelete($options, 'content-after')) {
            $controls .= $extraHtml;
        }

        // Add inline help
        if ($help = $this->arrDelete($options, 'help')) {
            $controls .= $this->help('inline', $help);
        }

        // Add error block
        if ($errors = $this->arrDelete($options, 'errors')) {
            $controls .= $this->errorBlock($errors);
            $class .= ' error';
        }

        // Add help block
        if ($helpBlock = $this->arrDelete($options, 'help-block')) {
            $controls .= $this->help('block', $helpBlock);
        }

        return $this->renderRow($label, $controls, $class, $options);
    }

    /**
     * Render an error block
     *
     * @param string $errors
     * @return string
     */
    private function errorBlock($errors)
    {
        return Html::tag('p', array('class' => 'help-block'), $errors);
    }

    /**
     * Returns error text for an attribute
     *
     * @param string $attribute
     * @return string
     */
    protected function errorTextFor($attribute)
    {
        if (!isset($this->errorGetter)) {
            return null;
        }

        $getter = $this->errorGetter;

        return $getter($attribute);
    }

    /**
     * Add append or prepend content to an input field
     *
     * @param string $inputHtml
     * @param string $prepend
     * @param string $append
     * @return string
     */
    private function prependAndAppend($inputHtml, $prepend, $append)
    {
        if (!$prepend && !$append) {
            return $inputHtml;
        }

        $wrapperClass = array();
        $output = array();

        if ($prepend) {
            $output[] = Html::tag('span', array('class' => 'add-on'), $prepend);
            $wrapperClass[] = 'input-prepend';
        }

        $output[] = $inputHtml;

        if ($append) {
            $output[] = Html::tag('span', array('class' => 'add-on'), $append);
            $wrapperClass[] = 'input-append';
        }

        return Html::tag('div', array('class' => $wrapperClass), implode('', $output), false);
    }

    /**
     * Render a control-group
     *
     * @param string $label Label HTML
     * @param string $controls HTML of controls
     * @param string $class Classes for the control group
     * @param array $attributes HTML attributes for the control group
     * @return string
     */
    protected function renderRow($label, $controls, $class, $attributes)
    {
        return sprintf(
            '<div class="control-group %s" %s>%s<div class="controls">%s</div></div>',
            $class,
            Html::attributes($attributes),
            $label,
            $controls
        );
    }

    /**
     * Renders a help element
     *
     * @param string $type inline or block
     * @param string|array $text The help text or an array of HTML attributes with an additional "text" key
     * @param array $attributes HTML attributes
     * @return string
     */
    protected function help($type, $text, $attributes = array())
    {
        if (empty($text)) {
            return '';
        }

        if (is_array($text)) {
            return $this->help($type, $this->arrDelete($text, 'text'), $text);
        }

        return $type === 'block'
            ? $this->helpBlock($text, $attributes)
            : $this->helpInline($text, $attributes);
    }

    /**
     * Render an inline help element
     *
     * @param string $text
     * @param array $attributes
     * @return string
     */
    protected function helpInline($text, $attributes = array())
    {
        return Html::tag('span', array_merge(array('class' => 'help-inline'), $attributes), $text);
    }

    /**
     * Render a block help element
     *
     * @param string $text
     * @param array $attributes
     * @return string
     */
    protected function helpBlock($text, $attributes = array())
    {
        return Html::tag('p', array_merge(array('class' => 'help-block'), $attributes), $text);
    }

    /**
     * Deletes a key from an array and returns the value
     *
     * Returns $default if the key is not set
     *
     * @param array $arr
     * @param string|int $key
     * @param mixed $default
     * @return mixed
     */
    private function arrDelete(array &$arr, $key, $default = null)
    {
        if (!isset($arr[$key])) {
            return $default;
        }

        $value = $arr[$key];
        unset($arr[$key]);

        return $value;
    }

}
