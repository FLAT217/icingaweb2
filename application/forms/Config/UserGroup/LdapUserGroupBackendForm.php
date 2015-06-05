<?php
/* Icinga Web 2 | (c) 2013-2015 Icinga Development Team | GPLv2+ */

namespace Icinga\Forms\Config\UserGroup;

use Icinga\Application\Config;
use Icinga\Authentication\User\UserBackend;
use Icinga\Authentication\UserGroup\LdapUserGroupBackend;
use Icinga\Data\ConfigObject;
use Icinga\Data\ResourceFactory;
use Icinga\Protocol\Ldap\Connection;
use Icinga\Web\Form;
use Icinga\Web\Notification;

/**
 * Form for managing LDAP user group backends
 */
class LdapUserGroupBackendForm extends Form
{
    /**
     * Initialize this form
     */
    public function init()
    {
        $this->setName('form_config_ldapusergroupbackend');
    }

    /**
     * Create and add elements to this form
     *
     * @param   array   $formData
     */
    public function createElements(array $formData)
    {
        $resourceNames = $this->getLdapResourceNames();
        $this->addElement(
            'select',
            'resource',
            array(
                'required'      => true,
                'autosubmit'    => true,
                'label'         => $this->translate('LDAP Connection'),
                'description'   => $this->translate('The LDAP connection to use for this backend.'),
                'multiOptions'  => array_combine($resourceNames, $resourceNames)
            )
        );
        $resource = ResourceFactory::create(
            isset($formData['resource']) && in_array($formData['resource'], $resourceNames)
                ? $formData['resource']
                : $resourceNames[0]
        );

        $userBackends = array('none' => $this->translate('None', 'usergroupbackend.ldap.user_backend'));
        $userBackendNames = $this->getLdapUserBackendNames($resource);
        if (! empty($userBackendNames)) {
            $userBackends = array_merge($userBackends, array_combine($userBackendNames, $userBackendNames));
        }
        $this->addElement(
            'select',
            'user_backend',
            array(
                'required'      => true,
                'autosubmit'    => true,
                'label'         => $this->translate('User Backend'),
                'description'   => $this->translate('The user backend to link with this user group backend.'),
                'multiOptions'  => $userBackends
            )
        );

        $groupBackend = new LdapUserGroupBackend($resource);
        if ($formData['type'] === 'ldap') {
            $defaults = $groupBackend->getOpenLdapDefaults();
            $disabled = null; // MUST BE null, do NOT change this to false!
        } else { // $formData['type'] === 'msldap'
            $defaults = $groupBackend->getActiveDirectoryDefaults();
            $disabled = true;
        }

        if (isset($formData['user_backend']) && $formData['user_backend'] !== 'none') {
            $userBackend = UserBackend::create($formData['user_backend']);
            $defaults->merge(array(
                'user_base_dn'          => $userBackend->getBaseDn(),
                'user_class'            => $userBackend->getUserClass(),
                'user_name_attribute'   => $userBackend->getUserNameAttribute(),
                'user_filter'           => $userBackend->getFilter()
            ));
            $disabled = true;
        }

        $this->createGroupConfigElements($defaults, $disabled);
        $this->createUserConfigElements($defaults, $disabled);
    }

    /**
     * Create and add all elements to this form required for the group configuration
     *
     * @param   ConfigObject    $defaults
     * @param   null|bool       $disabled
     */
    protected function createGroupConfigElements(ConfigObject $defaults, $disabled)
    {
        $this->addElement(
            'text',
            'group_class',
            array(
                'preserveDefault'   => true,
                'disabled'          => $disabled,
                'label'             => $this->translate('LDAP Group Object Class'),
                'description'       => $this->translate('The object class used for storing groups on the LDAP server.'),
                'value'             => $defaults->group_class
            )
        );
        $this->addElement(
            'text',
            'group_filter',
            array(
                'preserveDefault'   => true,
                'allowEmpty'        => true,
                'disabled'          => $disabled,
                'label'             => $this->translate('LDAP Group Filter'),
                'description'       => $this->translate(
                    'An additional filter to use when looking up groups using the specified connection. '
                    . 'Leave empty to not to use any additional filter rules.'
                ),
                'requirement'       => $this->translate(
                    'The filter needs to be expressed as standard LDAP expression, without'
                    . ' outer parentheses. (e.g. &(foo=bar)(bar=foo) or foo=bar)'
                ),
                'validators'        => array(
                    array(
                        'Callback',
                        false,
                        array(
                            'callback'  => function ($v) {
                                return strpos($v, '(') !== 0;
                            },
                            'messages'  => array(
                                'callbackValue' => $this->translate('The filter must not be wrapped in parantheses.')
                            )
                        )
                    )
                ),
                'value'             => $defaults->group_filter
            )
        );
        $this->addElement(
            'text',
            'group_name_attribute',
            array(
                'preserveDefault'   => true,
                'disabled'          => $disabled,
                    'label'         => $this->translate('LDAP Group Name Attribute'),
                'description'       => $this->translate(
                    'The attribute name used for storing a group\'s name on the LDAP server.'
                ),
                'value'             => $defaults->group_name_attribute
            )
        );
        $this->addElement(
            'text',
            'base_dn',
            array(
                'preserveDefault'   => true,
                'disabled'          => $disabled,
                'label'             => $this->translate('LDAP Group Base DN'),
                'description'       => $this->translate(
                    'The path where groups can be found on the LDAP server. Leave ' .
                    'empty to select all users available using the specified connection.'
                ),
                'value'             => $defaults->base_dn
            )
        );
    }

    /**
     * Create and add all elements to this form required for the user configuration
     *
     * @param   ConfigObject    $defaults
     * @param   null|bool       $disabled
     */
    protected function createUserConfigElements(ConfigObject $defaults, $disabled)
    {
        $this->addElement(
            'text',
            'user_class',
            array(
                'preserveDefault'   => true,
                'disabled'          => $disabled,
                'label'             => $this->translate('LDAP User Object Class'),
                'description'       => $this->translate('The object class used for storing users on the LDAP server.'),
                'value'             => $defaults->user_class
            )
        );
        $this->addElement(
            'text',
            'user_filter',
            array(
                'preserveDefault'   => true,
                'allowEmpty'        => true,
                'disabled'          => $disabled,
                'label'             => $this->translate('LDAP User Filter'),
                'description'       => $this->translate(
                    'An additional filter to use when looking up users using the specified connection. '
                    . 'Leave empty to not to use any additional filter rules.'
                ),
                'requirement'       => $this->translate(
                    'The filter needs to be expressed as standard LDAP expression, without'
                    . ' outer parentheses. (e.g. &(foo=bar)(bar=foo) or foo=bar)'
                ),
                'validators'        => array(
                    array(
                        'Callback',
                        false,
                        array(
                            'callback'  => function ($v) {
                                return strpos($v, '(') !== 0;
                            },
                            'messages'  => array(
                                'callbackValue' => $this->translate('The filter must not be wrapped in parantheses.')
                            )
                        )
                    )
                ),
                'value'             => $defaults->user_filter
            )
        );
        $this->addElement(
            'text',
            'user_name_attribute',
            array(
                'preserveDefault'   => true,
                'disabled'          => $disabled,
                    'label'         => $this->translate('LDAP User Name Attribute'),
                'description'       => $this->translate(
                    'The attribute name used for storing a user\'s name on the LDAP server.'
                ),
                'value'             => $defaults->user_name_attribute
            )
        );
        $this->addElement(
            'text',
            'user_base_dn',
            array(
                'preserveDefault'   => true,
                'disabled'          => $disabled,
                'label'             => $this->translate('LDAP User Base DN'),
                'description'       => $this->translate(
                    'The path where users can be found on the LDAP server. Leave ' .
                    'empty to select all users available using the specified connection.'
                ),
                'value'             => $defaults->user_base_dn
            )
        );
    }

    /**
     * Return the names of all configured LDAP resources
     *
     * @return  array
     */
    protected function getLdapResourceNames()
    {
        $names = array();
        foreach (ResourceFactory::getResourceConfigs() as $name => $config) {
            if (in_array(strtolower($config->type), array('ldap', 'msldap'))) {
                $names[] = $name;
            }
        }

        if (empty($names)) {
            Notification::error(
                $this->translate('No LDAP resources available. Please configure an LDAP resource first.')
            );
            $this->getResponse()->redirectAndExit('config/createresource');
        }

        return $names;
    }

    /**
     * Return the names of all configured LDAP user backends
     *
     * @param   Connection  $resource
     *
     * @return  array
     */
    protected function getLdapUserBackendNames(Connection $resource)
    {
        $names = array();
        foreach (Config::app('authentication') as $name => $config) {
            if (in_array(strtolower($config->backend), array('ldap', 'msldap'))) {
                $backendResource = ResourceFactory::create($config->resource);
                if (
                    $backendResource->getHostname() === $resource->getHostname()
                    && $backendResource->getPort() === $resource->getPort()
                ) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }
}
