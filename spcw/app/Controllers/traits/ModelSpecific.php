<?php

namespace App\Controllers\traits;

/**
 * ModelSpecific trait for controllers
 * 
 * Simply define the modelName property in the controller:
 * 
 *    protected $modelName = 'App\Models\BlogPostModel';
 * 
 * Then, call the getModel() method anywhere in the controller to get the model.
 *
 *    public function getIndex()
 *    {
 *        $data['items'] = $this->getModel()->findAll();
 *        return view('BlogPost/index', $data);
 *    }
 */

trait ModelSpecific
{
    /**
     * @var \CodeIgniter\Model|null The model that's holding this resource's data
     */
    protected $model;

    /**
     * Set or change the model this controller is bound to.
     * Given either the name or the object, determine the other.
     *
     * @param \CodeIgniter\Model|string|null $which
     *
     * @return void
     */
    public function setModel($which = null)
    {
        if ($which !== null) {
            $this->model     = is_object($which) ? $which : null;
            $this->modelName = is_object($which) ? null : $which;
        }

        if (empty($this->model) && ! empty($this->modelName) && class_exists($this->modelName)) {
            $this->model = model($this->modelName);
        }

        if (! empty($this->model) && empty($this->modelName)) {
            $this->modelName = $this->model::class;
        }
    }

    /**
     * Get the model this controller is bound to.
     * Initializes the model if it's not already.
     *
     * @return \CodeIgniter\Model|null The model that holding this resource's data
     */
    public function getModel()
    {
        if (empty($this->model)) {
            $this->setModel();
        }

        return $this->model;
    }
}
