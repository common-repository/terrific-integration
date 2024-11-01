(function($) {
    /**
     * <?php echo $skin; ?> Skin implementation for module <?php echo $module; ?>.
     *
     * @author <?php echo $current_user->user_firstname; ?> <?php echo $current_user->user_lastname; ?> <<?php echo $current_user->user_email; ?>>
     * @namespace Tc.Module.<?php echo $module; ?> 
     * @class <?php echo $skin; ?> 
     * @extends Tc.Module
     * @constructor
     */
    Tc.Module.<?php echo $module; ?>.<?php echo $skin; ?> = function(parent) {
        
		this.on = function(callback) {
            var self = this;
            var $ctx = self.$ctx;
			
            callback();
        };
        
        this.after = function() {
            
        };
		
    };
})(Tc.$);
