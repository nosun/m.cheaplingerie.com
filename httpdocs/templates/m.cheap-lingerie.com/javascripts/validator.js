
function setErrorMessage(obj, message) {
	obj.html('<div class="messages_box error"><p>' + message + '</p></div>');
}

function checkLogin(){
	var username = $('form[name="loginForm"] input[name="username"]').val();
	var messagesBox = $('.login_l .messages');
	if(username.length<= 0){
		setErrorMessage(messagesBox, 'Username/Email should not be empty!');
		messagesBox.css('display','block');
		return false;
	}
	if(!isCharNumber(username) && !isValidEmail(username)){
		setErrorMessage(messagesBox,'Username/Email should only contains numbers and letters!');
		messagesBox.css('display','block');
		return false;
	}
	return true;
}
function checkRegister(){
	var username = $('form[name="regForm"] input[name="username"]').val();
	var messagesBox = $('.sign_regist .messages');
	if(username.length<3){
		setErrorMessage(messagesBox, 'Username should not less than 3 characters!');
		messagesBox.css('display','block');
		return false;
	}else if(username.length>20){
		setErrorMessage(messagesBox,'Username should not more than 20 characters!');
		messagesBox.css('display','block');
		return false;
	}
	if(!isCharNumber(username)){
		setErrorMessage(messagesBox,'Username should only contains numbers and letters!');
		messagesBox.css('display','block');
		return false;
	}
	var email = $('form[name="regForm"] input[name="email"]').val();
	if (email.length <= 0) {
		setErrorMessage(messagesBox,'email address should not be empty!');
		messagesBox.css('display','block');
		return false;
	}
	if (!isValidEmail(email)) {
		setErrorMessage(messagesBox,'email address is invalid!');
		messagesBox.css('display','block');
		return false;
	}
	var password = $('form[name="regForm"] input[name="password"]').val();
	if(password.length<5){
		setErrorMessage(messagesBox,'password should not less than 5 characters!');
		messagesBox.css('display','block');
		return false;
	}else if(password.length>20){
		setErrorMessage(messagesBox,'password should not be over 20 characters!');
		messagesBox.css('display','block');
		return false;
	}
	var confirm_password = $('form[name="regForm"] input[name="confirm_password"]').val();
	if(confirm_password != password){
		setErrorMessage(messagesBox,'The two passwords are not match.');
		messagesBox.css('display','block');
		return false;
	}
	return true;
}

function checkChangePwd(){
	  if(document.changePwdForm.newpwd.value.length<5){
	      alert('password should not less than 5 characters!');
	      return false;
	  }else if(document.changePwdForm.newpwd.value.length>20){
	     alert('password should not be over 20 characters!!');
	        return false;
	  }
	   if(document.changePwdForm.newpwd2.value != document.changePwdForm.newpwd.value){
	        alert('The two passwords are not match.');
	        return false;
	    }
	    return true;
}

function checkGuestBook(){
	if(document.guestBookForm.nickname.value.length==0){
		alert('nickname can\'t empty!');
		return false;
	}
	if(document.guestBookForm.subject.value.length==0){
		alert('subject can not be empty');
		return false;
	}
	if(document.guestBookForm.comment.value.length==0){
        alert('comment can not be empty');
        return false;
    }
    return true;
}

function isCharNumber(s){
    if( s.search(/(^[a-z0-9A-Z]{3,20})$/)==-1 ) {
     	return false;
    }
   return true; 
}

function isValidEmail(email){
	var reg = /^\w+([-+.']\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/;
	if (reg.test(email)){
		return true;
	} else{
		return false;
	}
}

function waterMarkFocus(obj){
	var item = $(obj);
	if (item.hasClass('water_mark')) {
		item.removeClass('water_mark');
		item.val("");
	}
}

function waterMarkBlur(obj) {
	var item = $(obj);
	if ($.trim(item.val()).length <= 0){
		item.addClass('water_mark');
		item.val("If you have any other requirements for this item, please feel free to leave a message here.");
	}
}