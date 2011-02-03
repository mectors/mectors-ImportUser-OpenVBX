<?php

	if(isset($_POST['submitted'])){
		if(($file=$_FILES['file']['tmp_name'])&&is_uploaded_file($file))
			$import=file_get_contents($file);
		else
			$import=$_POST['data'];
		$import = preg_replace('/\r\n|\r/', "\n", $import);
		$lines=explode("\n",$import);
		$count=sizeof($lines);
	for($i=0;$i<$count;$i++)
	{
		$parts=explode(';',$lines[$i]);
		$firstname = $parts[0];
		$lastname = $parts[1];
		$email = $parts[2];
		$phone = $parts[3];
		$errors = array();
		$user = false;
		$error = false;
		$message = "Failed to save user for unknown reason.";
		$shouldGenerateNewPassword = false;
			
		if(trim($parts[0])!=""){
			try
			{
				PhoneNumber::validatePhoneNumber($phone);
			}
			catch(PhoneNumberException $e)
			{
				$error = true;
				$message = 'Not a valid phone number: '.$phone.' Please use international format.';
			}
			if (!$error)
			{
				$user = VBX_User::get(array('email' => $email));

				if(!empty($user) && $user->is_active == 1)
				{
					$error = true;
					$message = 'Email address:' .$user->email.' is already in use.';
				}
				elseif (!empty($user) && $user->is_active == 0)
				{
					// It's an old account that was made inactive.  By re-adding it, we're
					// assuming the user wants to re-instate the old account.
					$shouldGenerateNewPassword = true;
				}
				else
				{
					// It's a new user
					$user = new VBX_User();
					$shouldGenerateNewPassword = true;
				}

				

				if (!$error)
				{
			
					$user->first_name = $firstname;
					$user->last_name = $lastname;
					$user->email = $email;
					$user->is_admin = FALSE;
					$user->is_active = TRUE;
					$user->auth_type = 1;

					try
					{
						$user->save();
						if ($shouldGenerateNewPassword && !$error && !$user->set_password())
						{
							$error = true;
							$message = "Failed to generate new password.";
						}
					}
					catch(VBX_UserException $e)
					{
						$error = true;
						$message = $e->getMessage();
						error_log($message);
					}
			
					if (!$error)
					{
						if (strlen($phone) > 0)
						{
					
							// We're creating a new device record
						
							$number = array(
								"name" => "Primary Device",
								"value" => normalize_phone_to_E164($phone),
								"user_id" => $user->id,
								// sms is always enabled by default
								"sms" => 1
							);

							try
							{
								$new_device_id = $this->vbx_device->add($number);
							}
							catch(VBX_DeviceException $e)
							{
								$error = true;
								$message = "Failed to add device: " . $e->getMessage();
							}
						}
					} 
				}

			}
		}
		else
		{
			$error = true;
		}

	}
	$message=$i.' users were added successfully!';
	}
?>
<style>
	.vbx-import-flow form{
		margin-top:20px;
	}
	.vbx-import-flow p{
		margin:10px 0;
		padding:0 20px;
	}
</style>
<div class="vbx-content-main">
	<div class="vbx-content-menu vbx-content-menu-top">
		<h2 class="vbx-content-heading">Import Users</h2>
	</div><!-- .vbx-content-menu -->
    <div class="vbx-table-section vbx-import-flow">
<?php if($error): 
		echo("<p>Error while importing users: " .$message."</p>");
elseif($message):
	echo("<p>".$message."</p>"); 
endif; ?>
		<form method="post" action="" enctype="multipart/form-data">
			<fieldset class="vbx-input-container">
				<input type="hidden" name="submitted" value="true"/>
				<p><label class="field-label">File<br/><input type="file" name="file" class="medium" /></label></p>
				<p>or</p>
				<p><label class="field-label">Paste<br/><textarea rows="20" cols="100" name="data" class="medium">firstname;lastname;email;phone</textarea></label></p>
				<p><button type="submit" class="submit-button"><span>Import</span></button></p>
			</fieldset>
		</form>

    </div>

</div>