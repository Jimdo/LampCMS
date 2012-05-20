<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2012 (or current year) Dmitri Snytkine
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */



namespace Lampcms\Forms;

use \Lampcms\Validate;
use \Lampcms\Captcha\Captcha;

class Apiclient extends Form
{
	const CAPTCHA_ERROR = 'Incorrect image verification text<br/>Please try again';

	/**
	 * Name of form template file
	 * The name of actual template should be
	 * set in sub-class
	 *
	 * @var string
	 */
	protected $template = 'tplFormapiclient';


	/**
	 * Concrete form validator for this form
	 * (non-PHPdoc)
	 * @see Form::doValidate()
	 */
	protected function doValidate(){

		$this->validateCaptcha()
		->validateAppId()
		->validateTitle()
		->validateApptype()
		->validateIcon();
	}


	/**
	 *
	 * The value of hidden field app_id must be numberic
	 * else this is some type of hack attempt
	 *
	 * @throws \Lampcms\DevException
	 *
	 * @return object $this
	 */
	protected function validateAppId(){
		$appid = $this->Registry->Request->get('app_id', 's', null);
		if(!is_numeric($appid)){
			throw new \Lampcms\DevException('Something is wrong with the form. Unexpected value of app_id: '.$appid);
		}

		return $this;
	}


	/**
	 * User must select one
	 * of the radio buttons of the app_type
	 *
	 * @return object $this
	 */
	protected function validateApptype(){
		$type = $this->Registry->Request->get('app_type', 's', null);
		if(empty($type)){
			$this->setError('app_type', 'You must indicate the application type');
		} else if(!in_array($type, array('b',' c'))){
			if(empty($type)){
				$this->setError('app_type', 'Invalid application type');
			}
		}

		return $this;
	}


	/**
	 * Validate title length
	 *
	 * @return object $this
	 */
	protected function validateTitle(){
		$t = \trim($this->Registry->Request['app_name']);
		if(empty($t)){
			$this->setError('app_name', 'You must provide application name');
		}
		
		if( 500 < $len = \mb_strlen(\strip_tags($t))){
			$this->setError('app_name', 'App description must not exceed 500 characters. Your description was <strong>'.$len.'</strong> characters long');
		}

		if(\preg_match('/[^a-zA-Z0-9_\.\-\s]/', $t)){
			$this->setError('app_name', 'Invalid application name. Application name must contain only letters and numbers');
		}

		/**
		 * Check for uniqueness of app name but ONLY
		 * if this NOT an edit. In case of edit of cause
		 * the application with this name already exists - this one!
		 * 
		 */
		$id = $this->Registry->Request['app_id'];
		if(empty($id)){
			$a = $this->Registry->Mongo->API_CLIENTS->findOne(array('app' => $t));
			if(!empty($a)){
				$this->setError('app_name', 'There is already an APP with this name. Please choose different name for your app');
			}
		}
		
		return $this;
	}


	/**
	 * If form hasUploads and has uploaded file 'profile_image'
	 * then: check that if does not have 'error' code
	 * theck that the 'size' > 0 and 'tmp_name' !== 'none' and not empty
	 * check that size < (MAX_AVATAR_FILE_SIZE in setting)
	 * check that if 'type' not empty and is one of allowed image formats
	 * If any of this pre-checks fail then delete the uploaded file
	 * and set the form error
	 *
	 * @return object $this
	 */
	protected function validateIcon(){
		d('cp');
		if($this->hasUploads() && !empty($this->aUploads['icon'])){
			$a = $this->aUploads['icon'];

			if( !is_array($a) || (0 == $a['size'] && empty($a['name'])) ){
				d('avatar was not uploaded');

				return $this;
			}

			d('cp');

			/**
			 * If bad error code
			 */
			if(UPLOAD_ERR_OK !== $errCode = $a['error']){
				e('Upload of avatar failed with error code '.$a['error']);
				if(UPLOAD_ERR_FORM_SIZE === $errCode){
					$this->setError('icon', 'Uploaded file exceeds maximum allowed size');
					return $this;
				} elseif(UPLOAD_ERR_INI_SIZE === $errCode){
					$this->setError('icon', 'Uploaded file exceeds maximum upload size');
					return $this;
				} else {
					$this->setError('icon', 'There was an error uploading the avatar file');
					return $this;
				}
			} else {

				$aConfig = $this->Registry->Ini->getSection('API');
				$maxSize = $aConfig['MAX_ICON_UPLOAD_SIZE'];
				d('$maxSize '.$maxSize);

				/**
				 * Check If NOT an image
				 */
				if(!empty($a['type'])){
					if('image' !== substr($a['type'], 0, 5)){
						$this->setError('icon', 'Uploaded file was not an image');
						return $this;
					}elseif('image/gif' === $a['type'] && !\function_exists('imagecreatefromgif')){
						$this->setError('icon', 'Gif image format is not supported at this time. Please upload an image in JPEG format');
						return $this;
					} elseif('image/png' === $a['type'] && !\function_exists('imagecreatefrompng')){
						$this->setError('icon', 'PNG image format is not supported at this time. Please upload an image in JPEG format');
						return $this;
					}
				}


				/**
				 * If image too large
				 */
				if(!empty($a['tmp_name'])){
					if(false === $size = @\filesize($a['tmp_name'])){
						$this->setError('icon', 'There was an error uploading the avatar file');
						return $this;
					}

					d('size: '.$size);

					if(($size / $maxSize) > 1.1){
						d('$size / $maxSize: '.$size / $maxSize);
						$this->setError('icon', 'File too large. It must be under '.($maxSize/1024000).'MB');
					}
				}
			}
		}

		return $this;
	}



	/**
	 * Compare submitted captch string
	 * to actual captcha string
	 * 
	 */
	protected function validateCaptcha(){

		$Captcha = Captcha::factory($this->Registry->Ini);
		$res = $Captcha->validate_submit();

		/**
		 * If validation good then
		 * all is OK
		 */
		if(1 === $res){

			return $this;
		}

		/**
		 * If 3 then reached the limit of attampts
		 */
		if(3 === $res){
			throw new \Lampcms\CaptchaLimitException('You have reached the limit of image verification attempts');
		}

		/**
		 * @todo translate string
		 */
		$this->setFormError(self::CAPTCHA_ERROR);

		return $this;

	}

}
