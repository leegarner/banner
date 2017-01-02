<?php
/**
*   Class to handle banner ads.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
*   @package    banner
*   @version    0.2.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

/**
*   Define a class to deal with banners
*   @package banner
*/
class Banner
{
    /** Holder for the original ID, used when saving edits
    *   @var string */
    var $oldID;

    /** Publication dates
     *  @var string */
    var $uxdt_start, $uxdt_end;

    /** Indicate whether this is a new banner or not
     *  @var boolean */
    var $isNew;

    /** Indicate whether this is an admin or regular user
     *  @var boolean */
    var $isAdmin;

    /** Holder for the banner record properties
    *   @var array */
    var $properties = array();

    /** Database table name currently in use
    *   @var string */
    var $table;


    /**
     *  Constructor
     *  @param string $bid Banner ID to retrieve, blank for empty class
     */
    public function __construct($bid='', $table='')
    {
        global $_USER, $_GROUPS, $_CONF_BANR;

        $bid = COM_sanitizeID($bid, false);
        $this->setTable($table);

        if ($bid != '') {
            $this->Read($bid);
            $this->isNew = false;
        } else {
            // Set defaults for new record
            $this->isNew        = true;
            $this->enabled      = 1;
            $this->owner_id     = $_USER['uid'];
            $this->weight       = $_CONF_BANR['def_weight'];
            $this->perm_owner   = $_CONF_BANR['default_permissions'][0];
            $this->perm_group   = $_CONF_BANR['default_permissions'][1];
            $this->perm_members = $_CONF_BANR['default_permissions'][2];
            $this->perm_anon    = $_CONF_BANR['default_permissions'][3];
            $this->hits         = 0;
            $this->max_hits     = 0;
            $this->impressions  = 0;
            $this->max_impressions = 0;
            $this->tid          = 'all';

            /*if (isset($_GROUPS['Banner Admin'])) {
                $this->group_id = $_GROUPS['Banner Admin'];
            } else {
                $this->group_id = SEC_getFeatureGroup('banner.edit');
            }*/
        }

    }


    /** Setter function. Set values in the properties array.
    *
    *   @param  string  $key    Name of property to set
    *   @param  mixed   $value  Value to set
    */
    public function __set($key, $value)
    {
        switch ($key) {
        case 'owner_id':
        case 'grp_access':
        case 'hits':
        case 'max_hits':
        case 'impressions':
        case 'max_impressions':
        case 'weight':
        case 'ad_type':
            $this->properties[$key] = (int)$value;
            break;

        case 'bid':
        case 'camp_id':
            $this->properties[$key] = COM_sanitizeID($value, false);
            break;

        case 'cid':
        case 'tid':
            $this->properties[$key] = trim($value);
            break;

        case 'date':
            $this->properties[$key] = $value;
            break;

        case 'enabled':
            $this->properties[$key] = $value == 1 ? 1 : 0;
            break;

        case 'options':
            if (!is_array($value)) {
                $value = @unserialize($value);
                if (!$value) $value = array();
            }
            $this->properties[$key] = $value;
            break;

        case 'title':
        case 'notes':
            $this->properties[$key] = COM_checkHTML(COM_checkWords($value));
            break;
        }
    }


    /**
    *   Getter function. Returns a property value
    *
    *   @param  string  $key    Name of property to retrieve
       @return mixec           Value of property
    */
    public function __get($key)
    {
        if (isset($this->properties[$key])) {
            return $this->properties[$key];
        } else {
            return NULL;
        }
    }


    public function getID()
    {   return $this->bid;  }

    public function setAdmin($isadmin)
    {   $this->isAdmin = $isadmin ? true : false;   }

    public function setTable($table)
    {   $this->table = $table == 'bannersubmission' ? 'bannersubmission' : 'banner';
    }

    public function getOpt($name)
    {
        if (isset($this->options[$name])) {
            return $this->options[$name];
        } else {
            return NULL;
        }
    }

    /**
    *   Read a banner record from the database
    *
    *   @param  string  $bid    Banner ID to read (required)
    */
    public function Read($bid)
    {
        global $_TABLES;

        $A = DB_fetchArray(DB_query("
            SELECT *,
                UNIX_TIMESTAMP(publishstart) AS uxdt_start,
                UNIX_TIMESTAMP(publishend) AS uxdt_end
            FROM {$_TABLES[$this->table]}
            WHERE bid='".DB_escapeString($bid)."'", false));

        if (!empty($A)) {
            $this->isNew = false;
            $this->setVars($A, true);

            // Save the old ID for use in Update()
            $this->oldID = $this->bid;
        }
    }


    /**
    *   Set the banner variables from the supplied array.
    *   The array may be from a form ($_POST) or database record
    *
    *   @see    _CreateHTMLTemplate()
    *   @param  array   $A          Array of values
    *   @param  boolean $fromDB     Indicates if reading from DB or submission
    */
    public function setVars($A='', $fromDB=false)
    {
        global $_CONF_BANR, $_CONF;

        if (!is_array($A))
            return;

        if ($fromDB) {
            // Coming from the database
            $this->options = $A['options'];
            $this->weight = $A['weight'];
            $this->uxdt_start = $A['uxdt_start'];
            $this->uxdt_end = $A['uxdt_end'];
            $this->date = $A['date'];
        } else {
            // Coming from a form
            $this->options['url'] = COM_sanitizeUrl($A['url'],
                                    array('http','https'));
            $this->options['image_url'] = COM_sanitizeUrl($A['image_url'],
                            array('http','https'));
            $this->options['filename'] = COM_checkHTML(
                            COM_checkWords($A['filename']));

            // Save the Adsense image code
            if (isset($A['ad_code']) && !empty($A['ad_code'])) {
                $this->options['ad_code'] = $A['ad_code'];
            }
            $this->options['alt'] = COM_checkHTML(COM_checkWords($A['alt']));
            $this->options['width'] = (int)$A['width'];
            $this->options['height'] = (int)$A['height'];
            $this->options['target'] = $A['target'];

            // Assemble the dates from component parts
            if (!isset($A['start_dt_limit']) || $A['start_dt_limit'] != 1) {
                $this->uxdt_start = 0;
            } else {
                if ($_CONF['hour_mode'] == 12) {
                    if ($A['publish_ampm'] == 'pm') {
                        if ((int)$A['start_hour'] < 12) {
                            $A['start_hour'] += 12;
                        }
                    } elseif ((int)$A['start_hour'] == 12) {
                        $A['start_hour'] = 0;
                    }

                }

                $this->uxdt_start = mktime((int)$A['start_hour'],
                        (int)$A['start_minute'], 0,
                        (int)$A['start_month'], (int)$A['start_day'],
                        (int)$A['start_year']);
            }
            if (!isset($A['end_dt_limit']) || $A['end_dt_limit'] != 1) {
                $this->uxdt_end = 0;
            } else {
                if ($_CONF['hour_mode'] == 12) {
                    if ($A['end_ampm'] == 'pm') {
                        if ((int)$A['end_hour'] < 12) {
                            $A['end_hour'] += 12;
                        }
                    } elseif ((int)$A['end_hour'] == 12) {
                        $A['end_hour'] = 0;
                    }
                }

                $this->uxdt_end = mktime((int)$A['end_hour'],
                        (int)$A['end_minute'], 0,
                        (int)$A['end_month'], (int)$A['end_day'],
                        (int)$A['end_year']);
            }

            // Only admins can set some values, use the defaults for others
            if (SEC_hasRights('banner.edit')) {
                $this->weight = (int)$A['weight'];
            } else {
                $this->weight = (int)$_CONF_BANR['def_weight'];
            }

            // Create the HTML template for click tracking if this is
            // an HTML or Javascript ad.
            if ($this->ad_type == BANR_TYPE_SCRIPT) {
                $this->_CreateHTMLTemplate();
            }
        }

        $this->bid = $A['bid'];
        $this->cid = $A['cid'];
        $this->camp_id = $A['camp_id'];
        $this->ad_type = $A['ad_type'];

        $this->notes = $A['notes'];
        $this->title = $A['title'];
        //$this->publishstart = substr($A['publishstart'], 0, 16);
        //$this->publishend = substr($A['publishend'], 0, 16);
        $this->enabled = $A['enabled'] == 1 ? 1 : 0;
        $this->impressions = $A['impressions'];
        $this->max_impressions = $A['max_impressions'];
        $this->hits = $A['hits'];
        $this->max_hits = $A['max_hits'];
        $this->tid = $A['tid'];
        $this->owner_id = $A['owner_id'];
        $this->grp_access= $A['grp_access'];
    }


    /**
     *  Update the 'enabled' value for a banner ad.
     *  @param  integer $newval New value to set (1 or 0)
     *  @param  string  $bid    Optional ad ID.  Current object if blank
     *  @return boolean         True if toggled, False otherwise
     */
    public function toggleEnabled($newval)
    {
        global $_TABLES;

        if (!$this->hasAccess(3)) {
            return false;
        }

        $newval = $newval == 0 ? 0 : 1;
        DB_change($_TABLES['banner'],
                'enabled', $newval,
                'bid', DB_escapeString($this->bid));
        return true;
    }


    /**
     *  Update the impression (display) count.
     */
    public function updateImpressions()
    {
        global $_TABLES, $_CONF_BANR, $_USER;

        // Don't update the count for ads show to admins or owners, if
        // so configured.
        if (
            ($_CONF_BANR['cntimpr_admins'] == 0 &&
                SEC_hasRights('banner.admin'))
        || ($_CONF_BANR['cntimpr_owner'] == 0 &&
                $this->owner_id == $_USER['uid'])
        ) {
            return;
        }

        DB_query("UPDATE {$_TABLES['banner']}
                SET impressions=impressions+1
                WHERE bid='{$this->bid}'");
        DB_query("UPDATE {$_TABLES['bannercampaigns']}
                SET impressions=impressions+1
                WHERE camp_id='{$this->camp_id}'");
    }


    /**
    *   Increment the hit count.
    */
    public function updateHits()
    {
        global $_TABLES, $_CONF_BANR, $_USER;

        // Don't update the count for ads show to admins or owners, if
        // so configured.
        if (($_CONF_BANR['cntclicks_admins'] == 0 && SEC_hasRights('banner.admin'))
            || ($_CONF_BANR['cntclicks_owner'] == 0 && $this->owner_id == $_USER['uid'])
        ) {
            return;
        }

        // Update the banner hits
        $sql = "UPDATE {$_TABLES['banner']}
                SET hits=hits+1
                WHERE bid='{$this->bid}'";
        DB_query($sql);

        // Update the campaign total hits
        $sql = "UPDATE {$_TABLES['bannercampaigns']}
                SET hits=hits+1
                WHERE camp_id='{$this->camp_id}'";
        DB_query($sql);
    }


    /**
    *   Delete the current banner.
    *
    *   @param  string  $bid    Optional banner ID to delete
    */
    public function Delete()
    {
        global $_TABLES, $_CONF_BANR;

        if (!empty($this->options['filename']) &&
            file_exists($_CONF_BANR['img_dir'] . '/' . $this->options['filename'])) {
            @unlink($_CONF_BANR['img_dir'] . '/' . $this->options['filename']);
        }

        DB_delete($_TABLES[$this->table],
            'bid', DB_escapeString(trim($this->bid)));
        BANNER_auditLog("Deleted banner id {$this->bid}");
    }


    /**
    *   Returns the current user's access level to this banner
    *
    *   @return integer     User's access level (1 - 3)
    */
    public function Access()
    {
        global $_USER;

        if ($this->isNew) {
            return 3;
        }
        $access = SEC_hasAccess($this->owner_id, $this->group_id,
                    $this->perm_owner, $this->perm_group,
                    $this->perm_members, $this->perm_anon);
        return $access;
    }


    /**
    *   Determines whether the current user has a given level of access
    *   to this banner object.
    *
    *   @see    Access()
    *   @param  integer $level  Minimum access level required
    *   @return boolean     True if user has access >= level, false otherwise
    */
    public function hasAccess($level=3)
    {
        if ($this->Access() < $level) {
            return false;
        } else {
            return true;
        }
    }


    /**
    *   Save the current banner object using the supplied values.
    *
    *   @param  array   $A  Array of values from $_POST or database
    *   @return string      Error message, empty if successful
    */
    public function Save($A = array())
    {
        global $_CONF, $_GROUPS, $_TABLES, $_USER, $MESSAGE,
                $_CONF_BANR, $LANG12, $LANG_BANNER;

        if ($this->isNew) {
            if ( COM_isAnonUser() ||
                    ($_CONF_BANR['usersubmit'] == 0 &&
                    !SEC_hasRights('banner.submit'))
                ) {
                return $LANG_BANNER['access_denied'];
            }
        } else {
            if (!SEC_hasRights('banner.admin') &&
                !$this->hasAccess(3)) {
                return $LANG_BANNER['access_denied'];
            }
        }

        if (!empty($A)) {
            $this->setVars($A);
        }

        // Banner ID may be left blank, so generate one
        if ($this->bid == '') {
            $this->bid = COM_makesid();
        }

        // Make sure this isn't a duplicate ID.  If this is a user submission,
        // we also have to make sure this ID isn't in the main table
        // If updating a banner, check if the ID is in use, if it's changing
        $allowed = ($this->isNew || $this->bid != $this->oldID) ? 0 : 1;
        $num1 = DB_numRows(DB_query("SELECT bid
                    FROM {$_TABLES['banner']}
                    WHERE bid='{$this->bid}'"));
        if ($this->_isSubmission()) {
            $num2 = DB_numRows(DB_query("SELECT bid
                    FROM {$_TABLES['bannersubmission']}
                    WHERE bid='{$this->bid}'"));
        } else {
            $num2 = 0;
        }
        if ($num1 > $allowed || $num2 > 0) {
            return $LANG_BANNER['duplicate_bid'];
        }

        // Check required fields and return an error message if any
        // are missing.
        if (!$this->Validate($A)) {
            return $LANG12[23];
        }

        switch ($this->ad_type) {
        case BANR_TYPE_LOCAL:
            // Unset unused values
            unset($this->options['ad_code']);
            unset($this->options['image_url']);

            // Handle the file upload
            if (isset($_FILES['bannerimage']['name']) &&
                !empty($_FILES['bannerimage']['name'])) {
                USES_banner_class_category();
                USES_banner_class_image();

                $U = new Image($this->bid, 'bannerimage');
                $C = new Category($this->cid);
                $max_height = (int)$_CONF_BANR['img_max_height'];
                $max_width = (int)$_CONF_BANR['img_max_width'];
                if ($C->max_img_height > 0 && $C->max_img_height < $max_height) {
                    $max_height = $C->max_img_height;
                }
                if ($C->max_img_width > 0 && $C->max_img_width < $max_width) {
                    $max_width = $C->max_img_width;
                }
                $U->setMaxDimensions($max_width, $max_height);
                $U->uploadFiles();
                if ($U->areErrors() > 0) {
                    $this->options['filename'] = '';
                    //return BANNER_errorMessage($U->printErrors(false));
                    return $U->printErrors(false);
                } else {
                    $this->options['filename'] = $U->getFilename();
                }

                if (!empty($this->options['filename']) &&
                    ($this->options['width'] == 0 ||
                    $this->options['height'] == 0)) {
                    list($this->options['width'], $this->options['height']) =
                    @getimagesize($_CONF_BANR['img_dir'] . '/' .
                                    $this->options['filename']);
                }
            }
            break;

        case BANR_TYPE_REMOTE:
            unset($this->options['filename']);
            unset($this->options['ad_code']);
            break;

        case BANR_TYPE_SCRIPT:
            unset($this->options['filename']);
            unset($this->options['image_url']);
            unset($this->options['width']);
            unset($this->options['height']);
            unset($this->options['alt']);
            if (empty($this->options['url']))
                unset($this->options['url']);
            break;
        }

        if (empty($this->owner_id)) {
            // this is new banner from admin, set default values
            $this->owner_id = $_USER['uid'];
            $this->perm_owner = 3;
            $this->perm_group = 3;
            $this->perm_members = 2;
            $this->perm_anon = 2;
        }

        $access = $this->hasAccess(3);
        if ($access < 3) {
            COM_errorLog("User {$_USER['username']} tried to illegally submit or edit banner {$this->bid}.");
            return COM_showMessageText($MESSAGE[31], $MESSAGE[30]);
        }

        $publishstart = $this->uxdt_start == 0 ? 'NULL' :
                        "FROM_UNIXTIME({$this->uxdt_start})";
        $publishend = $this->uxdt_end == 0 ? 'NULL' :
                        "FROM_UNIXTIME({$this->uxdt_end})";
        $options = serialize($this->options);

        // Determine if this is an INSERT or UPDATE
        if ($this->isNew) {
            $sql1 = "INSERT INTO {$_TABLES[$this->table]} SET ";
            $sql3 = '';
            //$error = $this->Insert();
            /*if ($error == '') {
                if ($_CONF_BANR['notification'] == 1) {
                    $this->Notify();
                }
            }*/
        } else {
        //    $error = $this->Update();
            $sql1 = "UPDATE {$_TABLES[$this->table]} SET ";
            $sql3 = "WHERE bid='" . DB_escapeString($this->oldID) . "'";
        }
        $sql2 = "bid='" . DB_escapeString($this->bid) . "',
                cid='" . DB_escapeString($this->cid) . "',
                camp_id='" . DB_escapeString($this->camp_id) . "',
                ad_type='" . (int)$this->ad_type . "',
                options='" . DB_escapeString($options) . "',
                title='" . DB_escapeString($this->title). "',
                notes='" . DB_escapeString($this->notes). "',
                publishstart=$publishstart,
                publishend=$publishend,
                enabled='" . (int)$this->enabled . "',
                hits='" . (int)$this->hits . "',
                max_hits='" . (int)$this->max_hits . "',
                impressions='" . (int)$this->impressions . "',
                max_impressions='" . (int)$this->max_impressions . "',
                owner_id='" . (int)$this->owner_id . "',
                grp_access ='" . (int)$this->group_id . "',
                weight='" . (int)$this->weight . "',
                tid='" . DB_escapeString($this->tid) . "'";
        DB_query($sql1 . $sql2 . $sql3);
        if (!DB_error()) {
            $category = DB_getItem($_TABLES['bannercategories'], "category",
                    "cid='{$cid}'");
            COM_rdfUpToDateCheck('banner', $category, $bid);
            if ($this->isNew && $_CONF_BANR['notification'] == 1) {
                    $this->Notify();
            }
            return '';
        } else {
            return 'Database error saving banner';
        }
    }


    /**
    *   Insert a new record.
    */
    public function XInsert($checksubmission = true)
    {
        global $_TABLES, $LANG_BANNER;

        // Prepare the options for the database
        $options = serialize($this->options);

        // This gets used several times, so sanitize it once here.
        $s_bid = DB_escapeString($this->bid);

        $this->group_id = SEC_getFeatureGroup('banner.edit', 2);

        // Make sure this isn't a duplicate ID.  If this is a user submission,
        // we also have to make sure this ID isn't in the main table
        $num1 = DB_numRows(DB_query("SELECT bid
                    FROM {$_TABLES['banner']}
                    WHERE bid='$s_bid'"));
        if ($checksubmission) {
            $num2 = DB_numRows(DB_query("SELECT bid
                    FROM {$_TABLES['bannersubmission']}
                    WHERE bid='$s_bid'"));
        } else {
            $num2 = 0;
        }
        if ($num1 > 0 || $num2 > 0) {
            return $LANG_BANNER['duplicate_bid'];
        }

        /*$publishstart = empty($this->publishstart) ? 'NULL' :
                        "'".$this->publishstart."'";
        $publishend = empty($this->publishend) ? 'NULL' :
                        "'".$this->publishend."'";
        */
        $publishstart = $this->uxdt_start == 0 ? 'NULL' :
                        "FROM_UNIXTIME({$this->uxdt_start})";
        $publishend = $this->uxdt_end == 0 ? 'NULL' :
                        "FROM_UNIXTIME({$this->uxdt_end})";

        $sql = "INSERT INTO {$_TABLES[$this->table]} (
                    bid, cid, camp_id, ad_type, title, notes, date,
                    options, weight,
                    publishstart, publishend,
                    impressions, max_impressions, hits, max_hits,
                    enabled, owner_id, group_id,
                    perm_owner, perm_group, perm_members, perm_anon, tid
                ) VALUES (
                    '$s_bid',
                    '" . DB_escapeString($this->cid) . "',
                    '" . DB_escapeString($this->camp_id) . "',
                    '" . (int)$this->ad_type . "',
                    '" . DB_escapeString($this->title) . "',
                    '" . DB_escapeString($this->notes) . "',
                    NOW(),
                    '" . DB_escapeString($options) . "',
                    '" . (int)$this->weight . "',
                    " . $publishstart . ",
                    " . $publishend . ",
                    '" . (int)$this->impressions . "',
                    '" . (int)$this->max_impressions . "',
                    '" . (int)$this->hits . "',
                    '" . (int)$this->max_hits . "',
                    '" . (int)$this->enabled . "',
                    '" . (int)$this->owner_id . "',
                    '" . (int)$this->group_id . "',
                    '" . (int)$this->perm_owner . "',
                    '" . (int)$this->perm_group . "',
                    '" . (int)$this->perm_members . "',
                    '" . (int)$this->perm_anon . "',
                    '" . DB_escapeString($this->tid) . "'
        )";
        DB_query($sql, 1);
    }


    /**
    *   Update the current banner's database record
    */
    public function XUpdate()
    {
        global $_TABLES;

        // Prepare the options for the database
        $options = serialize($this->options);

        /*$publishstart = empty($this->publishstart) ? 'NULL' :
                        "'".$this->publishstart."'";
        $publishend = empty($this->publishend) ? 'NULL' :
                        "'".$this->publishend."'";
        */
        $publishstart = $this->uxdt_start == 0 ? 'NULL' :
                        "FROM_UNIXTIME({$this->uxdt_start})";
        $publishend = $this->uxdt_end == 0 ? 'NULL' :
                        "FROM_UNIXTIME({$this->uxdt_end})";

        $sql = "UPDATE {$_TABLES['banner']} SET
                bid='" . DB_escapeString($this->bid) . "',
                cid='" . DB_escapeString($this->cid) . "',
                camp_id='" . DB_escapeString($this->camp_id) . "',
                ad_type='" . (int)$this->ad_type . "',
                options='" . DB_escapeString($options) . "',
                title='" . DB_escapeString($this->title). "',
                notes='" . DB_escapeString($this->notes). "',
                publishstart=$publishstart,
                publishend=$publishend,
                enabled='" . (int)$this->enabled . "',
                hits='" . (int)$this->hits . "',
                max_hits='" . (int)$this->max_hits . "',
                impressions='" . (int)$this->impressions . "',
                max_impressions='" . (int)$this->max_impressions . "',
                owner_id='" . (int)$this->owner_id . "',
                group_id='" . (int)$this->group_id . "',
                perm_owner='" . (int)$this->perm_owner . "',
                perm_group='" . (int)$this->perm_group . "',
                perm_members='" . (int)$this->perm_members . "',
                perm_anon='" . (int)$this->perm_anon . "',
                weight='" . (int)$this->weight . "',
                tid='" . DB_escapeString($this->tid) . "'
            WHERE
                bid='" . DB_escapeString($this->oldID) . "'";
        DB_query($sql, 1);
    }


    /**
    *   Returns the banner id for a banner or group of banners
    *   Called as a standalone function: Banner::GetBanner($options)
    *
    *   @param  array   $fields Fields to use in where clause
    *   @return string          Banner id, empty for none available
    */
    public static function GetBanner($fields='')
    {
        global $_TABLES, $_CONF_BANR, $_CONF, $_USER;

        // Determine if any ads at all should be displayed to this user
        if (!self::canShow()) {
            return '';
        }

        $sql_cond = '';
        $limit_clause = '';
        if (is_array($fields)) {
            foreach ($fields as $field=>$value) {
                $value = DB_escapeString($value);
                switch(strtolower($field)) {
                case 'type':
                case 'category':
                case 'centerblock':
                    $sql_cond .= " AND c.{$field} = '$value'";
                    break;
                case 'tid':
                case 'topic':
                    if ($value != '' && $value != 'all') {
                        $sql_cond .= " AND c.tid IN ('$value', 'all')
                            AND camp.tid IN ('$value', 'all')
                            AND b.tid IN ('$value', 'all')";
                    } else {
                        $sql_cond .= " AND c.tid = 'all'
                            AND camp.tid = 'all'
                            AND b.tid = 'all'";
                    }
                    break;
                case 'campaign':
                    if ($value != '') {
                        $sql_cond .= " AND camp.camp_id='$value' ";
                    }
                    break;
                case 'limit':
                    $limit_clause = " LIMIT $value";
                    break;
                default:
                    $field = DB_escapeString($field);
                    $sql_cond .= " AND b.{$field} = '$value'";
                    break;
                }
            }
        }

        // Eliminate ads owned by the current user
        if ($_CONF_BANR['adshow_owner'] == 0) {
            $sql_cond .= " AND b.owner_id <> '" . (int)$_USER['uid'] . "'";
        }

        $sql = "SELECT b.bid, weight*RAND() as score
                FROM
                    {$_TABLES['banner']} AS b,
                    {$_TABLES['bannercategories']} AS c,
                    {$_TABLES['bannercampaigns']} AS camp
                WHERE b.cid=c.cid
                AND b.camp_id = camp.camp_id
                AND b.enabled = 1
                AND c.enabled = 1
                AND camp.enabled = 1
                AND (b.publishstart IS NULL OR b.publishstart < NOW())
                AND (b.publishend IS NULL OR b.publishend > NOW())
                AND (b.max_hits = 0 OR b.hits < b.max_hits)
                AND (b.max_impressions = 0 OR b.impressions < b.max_impressions)
                " . SEC_buildAccessSql('AND', 'b.grp_access') . "
                AND (camp.start IS NULL OR camp.start < NOW())
                AND (camp.finish IS NULL OR camp.finish > NOW())
                AND (camp.hits < camp.max_hits OR camp.max_hits = 0)
                AND (camp.max_impressions = 0
                    OR camp.impressions < camp.max_impressions)
                " . SEC_buildAccessSql('AND', 'c.grp_access') . ' '
                . $sql_cond .
                ' ORDER BY score DESC '
                . $limit_clause;

        //echo $sql;die;
        //COM_errorLog($sql);
        $banners = array();
        $result = DB_query($sql, 1);
        if ($result) {
            while ($A = DB_fetchArray($result, false)) {
                $banners[] = $A['bid'];
            }
            return $banners;
        } else {
            return '';
        }
    }


    /**
    *   Returns an array of the newest banners for the "What's New" block
    *
    *   @return array   Array of banner records
    */
    public static function GetNewest()
    {
        global $_TABLES, $_CONF_BANR;

        $A = array();
        if (!self::CanShow()) return $A;

        $sql = "SELECT bid
                FROM {$_TABLES['banner']}
                WHERE (date >= (DATE_SUB(NOW(),
                        INTERVAL {$_CONF_BANR['newbannerinterval']} DAY)))
                AND (publishstart IS NULL OR publishstart < NOW())
                AND (publishend IS NULL OR publishend > NOW()) " .
                COM_getPermSQL( 'AND' ) .
                ' ORDER BY date DESC LIMIT 15';

        $result = DB_query($sql);
        while ($row = DB_fetchArray($result)) {
            $A[] = $row['bid'];
        }
        return $A;
    }


    /**
    *   Creates the banner image and href link for display.
    *
    *   @param  string  $title      Banner Title, optional
    *   @param  integer $width      Image width, optional
    *   @param  integer $height     Image height, optional
    *   @param  boolean $link       True to create link, false for only image
    *   @return string              Banner Link
    */
    public function BuildBanner($title = '', $width=0, $height=0, $link = true)
    {
        global $_CONF, $LANG_DIRECTION, $_CONF_BANR, $LANG_BANNER;

        $retval = '';

        $url = COM_buildUrl(BANR_URL . '/portal.php?id=' . $this->bid);
        $target = isset($this->options['target']) ?
                    $this->options['target'] : '_blank';

        switch ($this->ad_type) {
        case BANR_TYPE_LOCAL:
        case BANR_TYPE_REMOTE:

            $class = 'ext-banner';
            if ((!empty($LANG_DIRECTION)) && ($LANG_DIRECTION == 'rtl')) {
                $class .= '-rtl';
            }
            $attr = array(
                    'title' => $title == '' ? $this->title : $title,
                    'class' => $class,
                    'alt' => $this->options['alt'] == '' ? $this->title : $this->options['alt'],
                    'target' => $target,
                    'data-uk-tooltip' => '',
                    //'border' => '0',
            );

            if ($this->ad_type == BANR_TYPE_LOCAL &&
                !empty($this->options['filename']) &&
                file_exists($_CONF_BANR['img_dir'] . '/' . $this->options['filename'])) {
                $img = $_CONF_BANR['img_url'] . '/banners/' . $this->options['filename'];
            } elseif ($this->ad_type == BANR_TYPE_REMOTE &&
                !empty($this->options['image_url'])) {
                $img = $this->options['image_url'];
            }

            if ($img != '') {
                if ($width == 0) $width = $this->options['width'];
                if ($height == 0) $height = $this->options['height'];

                $img = '<img width="' . $width . '" height="' . $height .
                        '" class="banner_img" src="' . $img . '" border="0" alt="' .
                        urlencode($this->options['alt']) . '" />';
                if ($link == true) {
                    $retval = COM_createLink($img, $url, $attr);
                } else {
                    $retval = $img;
                }
            }
            break;

        case BANR_TYPE_SCRIPT:
            if ($link == true) {

                if (!empty($this->options['htmlTemplate']) &&
                        !empty($this->options['htmlTemplate'])) {

                    $retval = str_replace(
                            array('{clickurl}', '{target}'),
                            array($url, $target),
                            $this->options['htmlTemplate']);
                } else {
                    $retval = $this->options['ad_code'];
                }

            } else
                $retval = $LANG_BANNER['ad_is_script'];

            break;

        }
        return $retval;
    }


    /**
    *   Determine the maximum number of days that a user may run an ad.
    *   Based on the account balance, unless either purchasing is disabled
    *   or the user is exempt (like administrators).
    *
    *   @param  integer $uid    User ID, current user if zero
    *   @return integer         Max ad days available, -1 if unlimited
    */
    public function MaxDaysAvailable($uid=0)
    {
        global $_TABLES, $_CONF_BANR, $_USER, $_GROUPS;

        if (!$_CONF_BANR['purchase_enabled'])
            return -1;

        $uid = (int)$uid;
        if ($uid == 0)
            $uid = $_USER['uid'];

        foreach ($_CONF_BANR['purchase_exclude_groups'] as $ex_grp) {
            if (array_key_exists($ex_grp, $_GROUPS)) {
                return -1;
            }
        }

        $max_days = (int)DB_getItem($_TABLES['banneraccount'],
                        'days_balance', "uid=$uid");
        return $max_days;
    }


    /**
    *   Validate this banner's url
    *
    *   @return string  Response, or empty if no test performed.
    */
    public function validateUrl()
    {
        global $LANG_BANNER_STATUS;

        // Have to have a valid url to check
        if ($this->options['url'] == '') return 'n/a';

        // Get the header and response code
        $ch = curl_init();
        curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_URL => $this->options['url'],
            )
        );
        curl_exec($ch);
        $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (array_key_exists($response, $LANG_BANNER_STATUS)) {
            return $response . ' ' . $LANG_BANNER_STATUS[$response];
        } else {
            return 'Unknown';
        }
    }


    /**
    *   Creates the edit form.
    *
    *   @param  string  $mode   Type of editing being done
    *   @return string          HTML for edit form
    */
    public function Edit($mode = 'edit')
    {
        global $_CONF, $_GROUPS, $_TABLES, $_USER, $_CONF_BANR, $_PLUGINS,
            $LANG_ACCESS, $MESSAGE, $LANG_BANNER, $LANG_ADMIN,
            $LANG12, $_SYSTEM;

        USES_banner_class_campaign();
        USES_banner_class_category();
        USES_banner_class_image();

        $retval = '';

        switch ($mode) {
        case 'edit':
            $saveoption = $LANG_ADMIN['save'];      // Save
            $sub_type = '<input type="hidden" name="item" value="banner" />';
            $cancel_url = $this->isAdmin ? BANR_ADMIN_URL . '/index.php' :
                $_CONF['site_url'];
        case 'submit':
            $saveoption = $LANG_ADMIN['save'];      // Save
            // override sub_type for submit.php
            $sub_type =
                '<input type="hidden" name="type" value="banner" />'
                .'<input type="hidden" name="mode" value="' .
                    $LANG12[8].'" />';
            $cancel_url = $this->isAdmin ? BANR_ADMIN_URL . '/index.php' :
                $_CONF['site_url'];
            break;

        case 'moderate':
            $saveoption = $LANG_ADMIN['moderate'];  // Save & Approve
            $sub_type = '<input type="hidden" name="type" value="submission" />';
            $cancel_url = $_CONF['site_admin_url'] . '/moderation.php';
            break;
        }

        $T = new Template(BANR_PI_PATH . '/templates/');
        $tpltype = $_CONF_BANR['_is_uikit'] ? '.uikit' : '';
        $T->set_file('editor',"bannerform$tpltype.thtml");

        $T->set_var(array(
            'help_url'      => BANNER_docUrl('bannerform'),
            'submission_option' => $sub_type,
            'lang_save'     => $saveoption,
            'cancel_url'    => $cancel_url,
        ));

        $weight_select = '';
        if ($this->isAdmin) {
            $T->set_var('action_url', BANR_ADMIN_URL . '/index.php');
            for ($i = 1; $i < 11; $i++) {
                $sel = $i == $this->weight ? 'selected="selected"' : '';
                $weight_select .= "<option value=\"$i\" $sel>$i</option>\n";
            }
        } else {
            if ($mode == 'submit') {
                $T->set_var('action_url', $_CONF['site_url'] . '/submit.php');
            } else {
                $T->set_var('action_url', BANR_URL . '/index.php');
            }
        }

        $access = $this->Access();
        if ($access == 0 OR $access == 2) {
            $retval .= COM_startBlock($LANG_BANNER['access_denied'], '',
                               COM_getBlockTemplate ('_msg_block', 'header'));
            $retval .= $LANG_BANNER['access_denied_msg'];
            $retval .= COM_endBlock (COM_getBlockTemplate ('_msg_block', 'footer'));
            COM_accessLog("User {$_USER['username']} tried to illegally submit or edit banner {$this->bid}.");
            return $retval;
        }

        $retval .= COM_startBlock ($LANG_BANNER['banner_editor'], '',
                               COM_getBlockTemplate ('_admin_block', 'header'));

        $T->set_var('banner_id', $this->bid);
        $T->set_var('old_banner_id', $this->oldID);
        if (!empty($this->bid) && SEC_hasRights('banner.edit')) {
            $T->set_var('can_delete', 'true');
        }

        if (!$this->isNew) {
            // Calculate display dimensions
            $disp_img = $this->BuildBanner('', $this->options['width'],
                        $this->options['height'], false);
            $T->set_var('disp_img', $disp_img);
        } else {
            $T->set_var('disp_img', '');
        }

        // Ad Type Selection
        $adtype_select = '';
        foreach ($LANG_BANNER['ad_types'] as $value=>$text) {
            $sel = $this->ad_type === $value ?
                        ' selected="selected"' : '';
            $adtype_select .= "<option value=\"$value\"$sel>$text</option>\n";
        }

        $T->set_var(array(
            'mootools'      => $_SYSTEM['disable_jquery'] ? 'true' : '',
            'banner_title' => htmlspecialchars($this->title),
            'max_url_length' => 255,
            'category_options' => Category::Dropdown(0, $this->cid),
            'campaign_options' => Campaign::Dropdown($this->camp_id),
            //'publishstart' => $this->publishstart,
            //'publishend' => $this->publishend,
            'banner_hits' => $this->hits,
            'banner_maxhits' => $this->max_hits,
            'impressions'   => $this->impressions,
            'max_impressions'   => $this->max_impressions,
            'ena_chk' => $this->enabled == 1 ? ' checked="checked"' : '',
            'image_url' => $this->options['image_url'],
            'alt'   => $this->options['alt'],
            'width' => $this->options['width'],
            'height' => $this->options['height'],
            'target_url' => $this->options['url'],
            'ad_code'   => $this->options['ad_code'],
            'adtype_select' => $adtype_select,
            'filename' => $this->options['filename'],
            'weight_select' => $weight_select,
            'sel'.$this->options['target'] => 'selected="selected"',
            'req_item_msg' => $LANG_BANNER['req_item_msg'],
            'perm_msg' => $LANG_ACCESS['permmsg'],
        ));

        if (!isset($this->tid)) {
            $this->tid = 'all';
        }
        $topics = COM_topicList('tid,topic', $this->tid, 1, true);
        $T->set_var('topic_list', $topics);
        $alltopics = '<option value="all"';
        if ($this->tid == 'all') {
            $alltopics .= ' selected="selected"';
        }
        $alltopics .= '>' . $LANG_BANNER['all'] . '</option>' . LB;
        $T->set_var('topic_selection',  $alltopics . $topics);

        // user access info
        if ($this->owner_id < 2) {
            $this->owner_id = $_USER['uid'];
        }

        if (SEC_hasRights('banner.admin')) {
            $T->set_var(array(
                'isAdmin'        => 'true',
                'owner_dropdown' => BANNER_UserDropdown($this->owner_id),
                'banner_ownerid' => $this->owner_id,
                'ownername'     => COM_getDisplayName($this->owner_id),
                'group_dropdown' => SEC_getGroupDropdown($this->grp_access, 3, 'grp_access'),
            ) );
        } else {
            $T->set_var(array(
                'isAdmin'       => '',
                'owner_id'      => $this->owner_id,
                'grp_access'    => $this->grp_access,
            ) );
        }

        $T->set_var('gltoken_name', CSRF_TOKEN);
        $T->set_var('gltoken', SEC_createToken());

        if ($this->uxdt_start == NULL) {
            $T->set_var(array(
                'start_dt_limit_chk'    => '',
                'startdt_sel_show'      => 'none',
                'startdt_txt_show'      => '',
            ) );
            $startdt = time();
        } else {
            $T->set_var(array(
                'start_dt_limit_chk'    => 'checked="checked"',
                'startdt_sel_show'      => '',
                'startdt_txt_show'      => 'none',
            ) );
            $startdt = $this->uxdt_start;
        }

        if ($this->uxdt_end == NULL) {
            $T->set_var(array(
                'end_dt_limit_chk'      => '',
                'enddt_sel_show'        => 'none',
                'enddt_txt_show'        => '',
            ) );
            $enddt = time();
        } else {
            $T->set_var(array(
                'end_dt_limit_chk'      => 'checked="checked"',
                'enddt_sel_show'        => '',
                'enddt_txt_show'        => 'none',
            ) );
            $enddt = $this->uxdt_end;
        }

        $startdt = new Date($startdt, $_USER['tzid']);
        $enddt = new Date($enddt, $_USER['tzid']);
        $h_fmt = $_CONF['hour_mode'] == 12 ? 'h' : 'H';
        $st_hour = $startdt->format($h_fmt, true);
        $end_hour = $enddt->format($h_fmt, true);

        $T->set_var(array(
            'start_hour_options' =>
                        COM_getHourFormOptions($st_hour, $_CONF['hour_mode']),
            'start_ampm_selection' =>
                        COM_getAmPmFormSelection('publish_ampm', $st_ampm),
            'start_month_options' => COM_getMonthFormOptions($startdt->format('m', true)),
            'start_day_options' => COM_getDayFormOptions($startdt->format('d', true)),
            'start_year_options' => COM_getYearFormOptions($startdt->format('Y', true)),
            'start_minute_options' =>
                        COM_getMinuteFormOptions($startdt->format('i', true)),
            'end_hour_options' =>
                        COM_getHourFormOptions($end_hour, $_CONF['hour_mode']),
            'end_ampm_selection' => COM_getAmPmFormSelection('end_ampm', $end_ampm),
            'end_month_options' => COM_getMonthFormOptions($enddt->format('m', true)),
            'end_day_options' => COM_getDayFormOptions($enddt->format('d', true)),
            'end_year_options' => COM_getYearFormOptions($enddt->format('Y', true)),
            'end_minute_options' => COM_getMinuteFormOptions($enddt->format('i', $true)),
        ) );
        $T->parse('output', 'editor');
        $retval .= $T->finish($T->get_var('output'));
        $retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $retval;
    }   // function Edit()


    /**
    *   Validate that the required fields are filled in.
    *
    *   @param  array   $A  All form variables
    *   @return boolean     True if valid, False otherwise
    */
    public function Validate($A)
    {
        // Must have a title
        if (empty($A['title']))
            return false;

        // Check that appropriate ad content has been added
        switch ($A['ad_type']) {
        case BANR_TYPE_LOCAL:
            if (empty($A['url']) || empty($_FILES))
                return false;
            break;
        case BANR_TYPE_REMOTE:
            if (empty($A['url']) || empty($A['image_url']))
                return false;
            break;
        case BANR_TYPE_SCRIPT:
            if (empty($A['ad_code']))
                return false;
            break;
        }

        return true;
    }


    /**
    *   Send an email notification for a new submission.
    */
    public function Notify()
    {
        global $_CONF, $_TABLES, $LANG_BANNER, $LANG08;

        $mailsubject = $_CONF['site_name'] . ' ' . $LANG_BANNER['banner_submissions'];
        $mailbody = $LANG_BANNER['title'] . ": $title\n";

        if ($this->table == 'bannersubmission') {
            $mailbody .= "$LANG_BANNER[10] <{$_CONF['site_admin_url']}/moderation.php>\n\n";
        } else {
            $mailbody .= "$LANG_BANNER[114] <" . BANR_URL .
                '/index.php?category=' . urlencode ($A['category']) . ">\n\n";
        }

        $mailbody .= "\n------------------------------\n";
        $mailbody .= "\n$LANG08[34]\n";
        $mailbody .= "\n------------------------------\n";

        COM_mail($_CONF['site_mail'], $mailsubject, $mailbody);
    }


    /**
    *   Determine if banners should be shown on this page or to this user.
    *
    *   @return boolean     True to show banners, False to not.
    */
    public static function canShow()
    {
        global $_CONF_BANR, $_CONF, $_USER;

        // Set some static variables since this function can be called
        // multiple times per page load.
        static $in_admin_url = 'X';
        static $is_admin = 'X';
        static $is_blocked_user = 'X';
        static $is_blocked_ip = 'X';

        // Check if this is an admin URL and the banner should be shown.
        if ($_CONF_BANR['show_in_admin'] == 0) {
            if ($in_admin_url === 'X') {
                $urlparts = parse_url($_CONF['site_admin_url']);
                if (stristr($_SERVER['REQUEST_URI'], $urlparts['path']) != false) {
                    $in_admin_url = true;
                } else {
                    $in_admin_url = false;
                }
            }
            if ($in_admin_url) {
                return false;
            }
        }

        // See if this is a banner admin, and we shouldn't show it
        if ($_CONF_BANR['adshow_admins'] == 0) {
            if ($is_admin === 'X') {
                $is_admin = SEC_hasRights('banner.admin') ? true : false;
            }
            if ($is_admin) {
                return false;
            }
        }

        // Now check if this user or IP address is in the blocked lists
        /*if (isset($_USER['uid']) && is_array($_CONF_BANR['users_dontshow'])) {
            if (in_array($_USER['uid'], $_CONF_BANR['users_dontshow'])) {
                return false;
            }
        }*/

        if (is_array($_CONF_BANR['ipaddr_dontshow'])) {
            if ($is_blocked_ip === 'X') {
                $is_blocked_ip = false;
                foreach ($_CONF_BANR['ipaddr_dontshow'] as $addr) {
                    if (strstr($_SERVER['REMOTE_ADDR'], $addr)) {
                        $is_blocked_ip = true;
                        break;
                    }
                }
            }
            if ($is_blocked_ip) return false;
        }

        if (is_array($_CONF_BANR['uagent_dontshow'])) {
            if ($is_blocked_user === 'X') {
                $is_blocked_user = false;
                foreach ($_CONF_BANR['uagent_dontshow'] as $agent) {
                    if (stristr($_SERVER['HTTP_USER_AGENT'], $agent)) {
                        $is_blocked_user = true;
                        break;
                    }
                }
            }
            if ($is_blocked_user) return false;
        }

        // Allow the site admin to implement a custom banner control function
        if (function_exists('CUSTOM_banner_control')) {
            if (CUSTOM_banner_control() == false) {
                return false;
            }
        }

        // Passed all the tests, ok to show banners
        return true;
    }


    /**
    *  Create the HTML template for javascript-based banners
    *
    *   @return string  HTML for the banner
    */
    private function _CreateHTMLTemplate()
    {
        $buffer = $this->options['ad_code'];
        if (empty($buffer))
            return;

        // Put our click URL and our target parameter in all anchors...
        // The regexp should handle ", ', \", \' as delimiters
        if (preg_match_all(
                '#<a(.*?)href\s*=\s*(\\\\?[\'"])http(.*?)\2(.*?) *>#is',
                $buffer, $m)) {
            foreach ($m[0] as $k => $v) {
                // Remove target parameters
                $m[4][$k] = trim(preg_replace(
                            '#target\s*=\s*(\\\\?[\'"]).*?\1#i',
                            '', $m[4][$k]));
                $urlDest = preg_replace(
                            '/%7B(.*?)%7D/', '{$1}',
                            "http" . $m[3][$k]);
                //$buffer = str_replace($v, "<a{$m[1][$k]}href={$m[2][$k]}{clickurl}$urlDest{$m[2][$k]}{$m[4][$k]} target={$m[2][$k]}{target}{$m[2][$k]}>", $buffer);
                $buffer = str_replace($v, "<a{$m[1][$k]}href={$m[2][$k]}{clickurl}{$m[2][$k]}{$m[4][$k]} target={$m[2][$k]}{target}{$m[2][$k]}>", $buffer);
            }

            $this->options['url'] = $urlDest;
            $this->options['htmlTemplate'] = $buffer;
        }
    }


    /**
    *   See if this is a banner submission or using the prod table
    *
    *   @return boolean     True if submission, False if prod
    */
    private function _isSubmission()
    {
        return $this->table == 'bannersubmission' ? true : false;
    }

}   // class Banner

?>
