<!DOCTYPE html>
   <body class="antialiased">
        <p>{!!$details['body']!!}</p>     
        <?php  $attachment = explode(',', $details['attachment']);?>   
        @if(isset($attachment))
        @foreach($attachment as $val)
        @if($val != null)
        <a href="{{$val}}" target="_blank"><img src="http://45.61.48.123/dropattachment/public//liveimage/fileicon.png"></a><br>
        @endif
        @endforeach
        @endif
    </body>
</html>
