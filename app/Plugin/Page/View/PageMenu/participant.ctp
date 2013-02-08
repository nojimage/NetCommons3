<div id="pages-menu-edit-participant-<?php echo($page['Page']['id']);?>" class="pages-menu-edit-view">
<div class="pages-menu-edit-participant">
	<div class="bold">
		<?php echo(__d('page', 'Edit members'));?>
	</div>
	<div class="top-description">
		<?php echo(__d('page', 'Set the roles of the room members, and press [Ok] button. To set the roles all at once, press [Select All] button.'));?>
	</div>
	<?php echo $this->Form->create(null, array('id' => 'pages-menu-edit-participant-form-'.$page['Page']['id'],
			'class' => 'pages-menu-edit-participant-form', 'data-ajax-replace' => '#pages-menu-edit-participant-'.$page['Page']['id'])); ?>
	<table id="pages-menu-edit-participant-grid-<?php echo($page['Page']['id']);?>" style="display:none;">
	</table>
	<?php
		echo $this->Html->div('submit',
			$this->Form->button(__('Ok'), array('name' => 'ok', 'class' => 'common-btn')).
			$this->Form->button(__('Cancel'), array('name' => 'cancel', 'class' => 'common-btn', 'type' => 'button',
				'onclick' => "$.PageMenu.hideDetail(".$page['Page']['id'].");", 'data-ajax-url' => $this->Html->url(array('plugin' => 'page', 'controller' => 'page_menu', 'action' => 'participant_cancel', $page['Page']['id'])),
				'data-ajax' => '#pages-menu-edit-participant-tmp'))
		);
		echo $this->Form->end();
	?>
</div>
<?php
echo $this->Html->css('plugins/flexigrid', null, array('inline' => true));
echo $this->Html->script('plugins/flexigrid', array('inline' => true));
?>
</div>
<script>
$(function(){
	$("#pages-menu-edit-participant-grid-<?php echo($page['Page']['id']);?>").flexigrid
    (
        {
            url: '<?php echo($this->Html->url(array('plugin' => 'page', 'controller' => 'page_menu', 'action' => 'participant_detail', $page['Page']['id']))); ?>',
            method: 'POST',
            dataType: 'json',
            showToggleBtn: false,
            colModel :
            [
                {display: __d('pages', 'Room members'), name : 'handle', width: 140, height: 44, sortable : true, align: 'left' },
                {display: '<?php echo($this->element('index/auth_list', array('auth' => $auth_list[NC_AUTH_CHIEF],   'user_id' => '0', 'selauth'=> true,  'radio'=> false, 'all_selected' => true, 'authority_id' => NC_AUTH_CHIEF_ID)));?>', name : 'chief', width: 120, sortable : true, align: 'center'  },
                {display: '<?php echo($this->element('index/auth_list', array('auth' => $auth_list[NC_AUTH_MODERATE],'user_id' => '0', 'selauth'=> true,  'radio'=> false, 'all_selected' => true, 'authority_id' => NC_AUTH_MODERATE_ID)));?>', name : 'moderator', width: 120, sortable : false, align: 'center'  },
                {display: '<?php echo($this->element('index/auth_list', array('auth' => $auth_list[NC_AUTH_GENERAL], 'user_id' => '0', 'selauth'=> true,  'radio'=> false, 'all_selected' => true, 'authority_id' => NC_AUTH_GENERAL_ID)));?>', name : 'general', width: 120, sortable : false, align: 'center'  },
                {display: '<?php echo($this->element('index/auth_list', array('auth' => $auth_list[NC_AUTH_GUEST],   'user_id' => '0', 'selauth'=> false, 'radio'=> false, 'all_selected' => true, 'authority_id' => NC_AUTH_GUEST_ID)));?>', name : 'guest', width: 120, sortable : false, align: 'center'  },
                {display: '<?php echo($this->element('index/auth_list', array('auth' => $auth_list[NC_AUTH_OTHER],   'user_id' => '0', 'selauth'=> false, 'radio'=> false, 'all_selected' => true, 'authority_id' => NC_AUTH_OTHER_ID)));?>', name : 'none', width: 120, sortable : false, align: 'center'  }
            ],
            sortname: "chief",
            sortorder: "desc",
            usepager: true,
            // useRp: true,
            rpOptions: <?php echo PAGES_PARTICIPANT_LIMIT_SELECT; ?>,
            rp: <?php echo PAGES_PARTICIPANT_LIMIT_DEFAULT; ?>,
            width: '810',
            height: 'auto',
            singleSelect: true,
            resizable : false,
            setParams : function() {
        		var fields = $(":input", $("#pages-menu-edit-participant-form-<?php echo($page['Page']['id']);?>")).serializeArray();
        		return fields;
        	},
            onSuccess : function() {

        	}
        }
    );
});
</script>