<?php $userInfo = session('user_info'); ?>

<nav class="navbar navbar-default" style="margin-bottom: 6px;">
    <div class="container-fluid">
        <!-- Brand and toggle get grouped for better mobile display -->
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="http://portal.lifewatchgreece.eu">
                <img src="{{ asset('images/lfw_logo.png') }}" style="width: 30px; display: inline-block" />
                <span style="margin-left: 10px; color: #16737B">Lifewatch Greece Project</span>
            </a>
        </div>
    </div><!-- /.container-fluid -->
</nav>