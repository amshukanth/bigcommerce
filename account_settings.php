<?php

session_start();

if(isset($_GET['store_hash'])&isset($_SESSION['storeHash'])){
    $storeHash = $_GET['store_hash'];
}
else if(isset($_SESSION['storeHash'])){
    $storeHash = $_SESSION['storeHash'];
}
else if(isset($_GET['store_hash'])){
    $storeHash = $_GET['store_hash'];
}

?>
<link rel="stylesheet" href="../agile_settings.css"/>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css"/>
<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700" rel="stylesheet">


<section id="section-settings">
    <div class="section-content">
        <h1 class="section-header">
        <img src="../agile-crm.png"/>
        </h1>
        <h3>Do not have an account with Agile CRM?
        <a target="_blank" href="https://www.agilecrm.com/pricing?utm_source=bigcommerce&utm_medium=website&utm_campaign=integration" style="text-decoration:underline;padding:5px;color:cornflowerblue">Create account</a>
        </h3>
    </div>
    <div class="settings-section">
        <div class="container">
         <form id="agile_settings_form" action="/agile_settings" method="GET">
            <div class="col-md-6 form-line">
                <div class="form-group">
                    <label for="bigcommerceStoreName">Bigcommerce Store</label>
                    <input type="text" class="form-control" id="" name="bigcommerce-store-name" value="store-<?=@$storeHash;?>"/>
                </div>
                <div class="form-group">
                    <label for="agileDomain">Agile Domain</label>
                    <input type="text" class="form-control" name="agile_domain" id="agile_domain" value="<?=@$_GET['agile_domain'];?>" />
                    <small>If domain is xxxx.agilecrm.com, then enter xxxx</small>
                </div>
                <div class="form-group">
                    <label for="agileEmail">Email</label>
                    <input type="email" class="form-control" name="agile_email" id="agile_email" value="<?=@$_GET['agile_email'];?>" />
                </div>  
                <div class="form-group">
                    <label for="agileRestApiKey">Rest API Key</label>
                    <input type="text" class="form-control" name="agile_rest_api_key" id="agile_rest_api_key" value="<?=@$_GET['agile_rest_api_key'];?>" />
                    <small>Go to Agile Dashboard&gt;Admin Settings&gt;Developers&amp;API&gt;Rest API Key</small>
                </div>
                <div class="form-group">
                    <label for="syncCustomers">Sync Customers as Contacts in Agile</label>
                     <input type="checkbox" name="sync_customers" <?php if(@$_GET['sync_customers']=="yes") { ?> checked <?php } ?>/>                     
                </div>
                <div class="form-group">
                    <label for="syncOrders">Sync Orders as notes to Agile</label>
                     <input type="checkbox" name="sync_orders" <?php if(@$_GET['sync_orders']=="yes") { ?> checked <?php } ?>/>
                </div>
                <input type="hidden" name="store_hash" value="<?=@$storeHash;?>">
                <div class="form-group">
                    <input type="submit" id="submit_button" class="btn btn-default submit" value="Save" />
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    <span for="agileConnectMessage" id="agile_connect_message" style="line-height:40px"></span>
                </div>
            </div>
            <?php
            if(!empty($_GET['agile_domain'])&!empty($_GET['agile_rest_api_key'])){
            ?>
            <div class="col-md-6">
                <div class="form-group">
                    <label for ="description"> Please copy and paste the below script in footer scripts to enable the webrules/webstats for your store</label>
                    <textarea class="form-control" id="agile_script" cols="" style="background: oldlace;height:320px"  placeholder="Enter Your Message"><?php
                    echo htmlentities('<script id="_agile_min_js" async type="text/javascript" src="https://'.@$_GET['agile_domain'].'.agilecrm.com/stats/min/agile-min.js"></script>
<script type="text/javascript">
var Agile_API = Agile_API || {}; Agile_API.on_after_load = function(){
_agile.set_account("'.@$_GET['agile_rest_api_key'].'", "'.@$_GET['agile_domain'].'");
_agile.set_email("%%GLOBAL_CurrentCustomerEmail%%"); //For Blueprint Stores
_agile.set_email("{{customer.email}}"); //For Stencil Stores
_agile_execute_web_rules(); //to enable webrules, otherwise comment this line
_agile.track_page_view();   //to enable webstats, otherwise comment this line
};
</script>​'); 
}
?> </textarea>
                </div>
            </div>
         </form>
        </div>
    </div>
</section>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://ajax.aspnetcdn.com/ajax/jQuery/jquery-3.2.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script>
window.onload = function(){
    document.getElementById('submit_button').onclick = function(e)
    {

        e.preventDefault();
        var agile_domain = $("#agile_domain").val();
        var email = $("#agile_email").val();
        var key = $("#agile_rest_api_key").val();

        domain_regexp = /^[a-zA-Z0-9]+$/;
        rest_api_key_regexp = /^[a-zA-Z0-9]+$/;
        email_regexp = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

        if(agile_domain.length == 0||key.length == 0||email.length == 0){
            $("#agile_connect_message").text("One of the fields is empty. Please fill all the fields");
            $("#agile_connect_message").css({ "color" : "black"});
            return false;
        }
        else if(!agile_domain.match(domain_regexp)){
            $("#agile_connect_message").text("Invalid Domain");
            $("#agile_connect_message").css({ "color" : "black"});
            return false;
        }
        else if(!key.match(rest_api_key_regexp)){
            $("#agile_connect_message").text("Invalid API Key");
            $("#agile_connect_message").css({ "color" : "black"});
            return false;
        }
        else if(!email.match(email_regexp)){
            $("#agile_connect_message").text("Invalid Email");
            $("#agile_connect_message").css({ "color" : "black"});
            return false;
        }
        jQuery.ajax({ 
            
            url : 'https://' + agile_domain + '.agilecrm.com/core/js/api/email?id=' + key + '&email=as', 
            type : 'GET', 
            dataType : 'jsonp',
            success : function(json)
            {
                if (json.hasOwnProperty('error')){
                    $("#agile_connect_message").text("Invalid api key or domain name");
                    $("#agile_connect_message").css({ "color" : "black"});                
                }
                else{
                   /* $("#agile_connect_message").text("Validation Successful");
                    $("#agile_connect_message").css({ "color" : "yellow"});  */
                    $("#agile_settings_form").submit();                  
                }   
                return;     
            } 
        });

    };

    document.getElementById("agile_script").onclick = function(){
        $("#agile_script").select();
        document.execCommand('copy');
    };
}

</script>