(function($) {
    /**
     * <?php echo $module; ?> module implementation.
     *
     * @author <?php echo $current_user->user_firstname; ?> <?php echo $current_user->user_lastname; ?> <<?php echo $current_user->user_email; ?>>
     * @namespace Tc.Module
     * @class <?php echo $module; ?> 
     * @extends Tc.Module
     */
    Tc.Module.<?php echo $module; ?> = Tc.Module.extend({
	
        on: function(callback) {
            var self = this;
            var $ctx = self.$ctx;
			
            callback();
        },
        
        after: function() {
            
        }
		
    });
})(Tc.$);