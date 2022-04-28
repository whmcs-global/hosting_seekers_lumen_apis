<?php

return [

    'secrect' => env('ENC_DEC_SECRET', ''),
    'encryptionMethod' => env('ENC_DEC_METHOD', ''),
    'hostKey' => env('HOST_KEY', ''),
    //Error message
    'ERROR' => [
        'OOPS_ERROR' => 'Oops!! Something went wrong.',
        'TRY_AGAIN_ERROR' => 'Oops!! Something went wrong. Try again later.',
        'FORBIDDEN_ERROR' => 'Oops!! Something went wrong.',
        'TOKEN_INVALID' => 'This password reset token is invalid.',
        'WRONG_CREDENTIAL' => 'Please provide valid credentials.',
        'ACCOUNT_ISSUE' => 'Oops! your account is not active yet. Please verify your email or contact administrator.',
        'IMAGE_TYPE' => 'Please select png or jpg type image.',
        'PASSWORD_MISMATCH' => 'Current Password Does not Match',
        'PASSWORD_SAME' => 'New Password cannot be same as your current password',
        'IP_ISSUE' => "You can't review this company due to ip confict. For more details contact administrator",
        'DELETE_ERROR' => 'You can not delete this entry as it is associate with one of the other entry',
    ],
    'SUCCESS' => [
        'UPDATE_DONE' => 'has been updated successfully.',
        'CREATE_DONE' => 'has been created successfully.',
        'SENT_DONE' => 'has been sent successfully.',
        'SUBMIT_DONE' => 'has been submitted successfully.',
        'DELETE_DONE' => 'has been deleted successfully.',
        'STATUS_UPDATE' => 'status has been updated successfully.',
        'REPLY_SENT' => 'Reply has been sent successfully.',
        'RESET_LINK_MAIL' => 'We have sent you an email with password reset link.',
        'CONTACT_DONE' => 'You message has been sent successfully. Waiting for administrator reply',
        'WELCOME' => 'Thank you for verifing you email.',
        'WELCOME_LOGIN' => 'Thank you for verifing you email. Login to your account.',
        'ACCOUNT_CREATED' => 'Welcome to HostingSeekers, your account has been created successfully please verify your email first for login.',
        'ACCOUNT_UPDATE' => 'Welcome to HostingSeekers, your account has been created successfully please update your details.',
        'ACCOUNT_SUSPEND' => 'Your account has been suspended on your request. Your account will be disabled from listing for 30 days. After 30 days your account will be deleted permanently, if you did not reactivate. You can reactivate your Hostingseekers account at any time by logging back into Hostingseekers.',
    ],
    'PAGINATION_NUMBER' => '12',
    'REVIEWS_NUMBER' => '120',
    'RATING_NUMBER' => '4.5',
    'VIEWS_NUMBER' => '5',
    'DAYS_FOR_YEARLY_BILLING' => '30',
    'DAYS_FOR_MONTHLY_BILLING' => '3',
    'CC_EMAIL' => 'shivkaggarwal@shinedezign.com',
    'ADMIN_EMAIL' => 'info@hostingseekers.com',
    'SENDER' => 'Anjana Joshi',
    'DESIGNATION' => 'Marketing Executive',
    'HOMEPAGE_AD_EXPIRE_TIME' => 86400000,
    'NODE_URL' => "https://www.hostingseekers.com/c-review",
    'TICKET_URL' => "https://support.hostingseekers.com",
    'SES_URL' => "https://www.hostingseekers.com/c-review/send/emails-ses",
    'SMTP_URL' => "https://www.hostingseekers.com/c-review/send/emails-smtp",
    'SENT_THROUGH_SMTP' => TRUE
    

];
