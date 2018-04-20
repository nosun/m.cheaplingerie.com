/*可折叠按钮组*/
	$('.outer-title').bind('click',function () {
		$(this).siblings('.collapse-inner').slideToggle();
		$(this).siblings('.p-arrow').toggleClass('expand');
	})
	
	$(function(){
        var len = $('.outer-title').length;
        for (var i = 0; i < len; i++) {
            if ($('.outer-title').eq(i).siblings('.collapse-inner').find('li').length<1) {
                $('.outer-title').eq(i)
                .siblings('.p-arrow')
                .css("background-image","url('/templates/m.cheap-lingerie.com/images/arrow-r.png')");
            };
        };
    });
	
/*遮罩层*/
	$('.btn-num').click(function(){
        pro_pid = $(this).parents('li').eq(0).attr('pid');//获得产品的pid
		$('.backDrop').show();
		$('.backDrop').bind("touchmove",function(e){
	        e.stopPropagation();
	    });

		//$('.backDrop ul').unbind("touchmove");
		$('.backDrop ul li')
			.removeClass('active')
			.eq($(this).find('.num').html()-1)
			.addClass('active');
		  $(this).addClass('btn-num-current');
	})
	$('.backDrop-bg').click(function(){
		$('.backDrop').hide();
		$(window).unbind("touchmove");
	})
	$('.backDrop p span').click(function(){
        $('.btn-num').removeClass('btn-num-current');
		$('.backDrop').hide();
		$(window).unbind("touchmove");
	})
	$('.backDrop ul li').click(function(){
		var selectNum = $('.backDrop ul li').index($(this)) + 1;
		if (selectNum !== 10) {
            pro_quantity = $(this).html();//获得10以下的产品数量
			$('.btn-num-current').find('.num').html($(this).html());
		}else{
			$('.btn-num-current')
				.siblings('.text-default')
				.show().val(10).focus();
			$('.btn-num-current').hide();
			//$('.text-default').show();
		}


		
		$('.btn-num-current').removeClass('btn-num-current');
		$('.backDrop').hide();
		$(window).unbind("touchmove");
	})

/*付款方式 Shiping Method 选择*/
	$('.method-list li').click(function(){
		$('.method-list li').removeClass('active');
		$(this).addClass('active');
	})
    $('.method-list2 li').click(function(){
        $('.method-list2 li').removeClass('active');
        $('.method-list2').find('div').hide();
        $(this).next().addClass('shipping-information').show();
        $(this).addClass('active');
    })
/*method switch*/
    $('.btn-shipping-address').click(function(){
        $('.shipping_address').removeClass('active').hide();
        $('#shipping_method').addClass('active').show();
    })

    $('.btn-shipping-method').click(function(){
        $('#shipping_method').removeClass('active').hide();
        $('#pay_method').addClass('active').show();

    })
/*method back 动作*/
    $('.back_head').click(function(){
        if($('#shipping_method').hasClass('active')){
            $('#shipping_method').removeClass('active').hide();
            $('.shipping_address').addClass('active').show();
        }

        if($('#pay_method').hasClass('active')){
            $('#pay_method').removeClass('active').hide();
            $('#shipping_method').addClass('active').show();
        }

    })



 /*定义一级页点击选择颜色尺寸数量button 函数*/
    function select (id) {
        $('#'+id).click(function(){
            history.pushState(null, id, window.location.href + "#="+id);
            $('.MAIN').hide();
            $('.'+id).show();
            $("body,html").animate({
                scrollTop:0
            },0);

            //点击相应的模块后   再动态定义点击back的时侯  哪个再次显示隐藏
            window.onpopstate = function(event){
                $('.'+id).fadeOut(100);
                $('.MAIN').fadeIn(300);
                $('#SIZE').scrollTop(0);
            }
        })
    }

/*定义二级页具体颜色尺寸选择*/
    function innerselect(content){
        $('.'+content+'_select li').click(function(){
            $('.'+content+'_select li').removeClass('clicked');
            $(this).addClass('clicked');
            var words = $(this).children('span').attr("val");
            window.onpopstate = function(){
              $('.'+content.toUpperCase()).fadeOut(100);
              $('.MAIN').fadeIn(300);
              $('#'+content.toUpperCase()).find('.sele').html(words);
              $('#SIZE').scrollTop(0);
              $(function() {
                $(".flexslidertwo").flexslider({
                  animation: "slide",
                  animationLoop: false,
                  itemWidth: 100,
                  itemMargin: 5
                });
              });
              $(function() {
                $(".flexsliderthree").flexslider({
                  animation: "slide",
                  slideshow:true,
                  slideshowSpeed:5000,
                  itemMargin: 5
                });
              });
            }
            //if(words.indexOf("As Picture") >= 0){
            var flag = $(this).children('img').length;
            if(flag != 0){
              var img = $(this).children('img')[0].src;
            }
              
            //}
            $('.loadding').show();
            $.ajax({
              type:"POST",
              url: "product/ajaxsetproperty/",
              data : { type : content, value : words },
              success: function(data){
                var backdata = eval("(" + data + ")");
                if(backdata.msg == "success"){
                  $('#data' + content + 'input').val(words);
                  if(content == "color"){
                    if(words.indexOf("As Picture") >= 0){
                      words = words.substr(0, words.indexOf(":"));
                      $("#colorpic").css("display", "block");
                      $('#' + content.toUpperCase()).find('img')[0].src = img;
                    }
                    else{
                      if(flag != 0){
                        $("#colorpic").css("display", "block");
                        $('#' + content.toUpperCase()).find('img')[0].src = img;
                      }else{
                        $("#colorpic").css("display", "none");
                      }
                    }
                  }
                  history.back();
                  $('.loadding').hide();
                }
              }
            });
        });
    }
/*鐐瑰嚮quantity 鐨� GO 閫夋嫨鏁伴噺*/
    $('.go').click(function(){
        quantity = $(this).prev().val();
        var first = quantity.toString().split('')[0];
        if(quantity!='Quantity' && quantity>0 && first>0){
            $(this).prev().removeClass('error')
            var quantity = $(this).prev()[0].value;
            history.back();
            $('#QUANTITY').find('.sele').html(quantity);
        }
        else{
            $(this).prev().addClass('error');
            alert('error');
        }
    });
/*鐐瑰嚮back 璋冪敤back()*/
   // $('.back').click(function(){
   //    history.back();
   // });
/*鏀惧ぇ鍥剧墖*/
    $('.goodbay img').on('doubletap', function(event) {
        if($(this).hasClass('expanded')){
            var width = $(this).width();
            var height = $(this).height();
            $(this).removeClass('expanded')
                .animate({height:height/2+"px",width:width/2+"px","marginLeft":"0px","marginTop":"0px"});
        }else{
            var width = $(this).width();
            var height = $(this).height();
            var h = height*2;
            var w = width*2;
            $(this).addClass('expanded');
            handlePosition(event,w,h);
        }

    });


    function handlePosition(event,w,h){
        //画布的宽高
        var outWidth = $('.goodbay').width();
        var outHeight = $('.goodbay').height();
        //坐标比例
        var x = event.x/outWidth;
        var y = event.y/outHeight;
        //放大后的偏移量
        var offsetX = (w*x - event.x)*(-1);
        var offsetY = (h*y - event.y)*(-1);
        $('.expanded').animate({height:h+"px",width:w+"px","marginLeft":offsetX+"px","marginTop":offsetY+"px"});
    }

    var preX = 0,
        preY = 0;

    $('body').on('drag', '.expanded', function(e) {
        //画布的宽高
        var outWidth = $('.goodbay').width();
        var outHeight = $('.goodbay').height();
        //放大后的宽高
        var bigx = $('.expanded').width();
        var bigy = $('.expanded').height();
        //边界值
        var edgeX = (-1)*(bigx - outWidth);
        var edgeY = (-1)*(bigy - outHeight);
        //偏移值
        var ml = parseInt($('.expanded').css("marginLeft"));
        var mt = parseInt($('.expanded').css("marginTop"));

        if(preX!==0 && preY !==0){
            preX = e.x - preX;
            preY = e.y - preY;
        }

        //移动后的坐标
        var finalX = ml+preX;
        var finalY = mt+preY;

        if(finalX>=0){
            $('.expanded').css({"marginLeft": "0px"});
        }else if(finalX<0 && finalX>edgeX){
            $('.expanded').css({"marginLeft": finalX+"px"});
        }else{
            $('.expanded').css({"marginLeft": edgeX+"px"});
        }

        if(finalY>=0){
            $('.expanded').css({"marginTop": "0px"});
        }else if(finalY<0 && finalY>edgeY){
            $('.expanded').css({"marginTop": finalY+"px"});
        }else{
            $('.expanded').css({"marginTop": edgeY+"px"});
        }

        if(e.end){
            preX=0;
            preY=0;
        }else{
            preX = e.x ;
            preY = e.y ;
        }
        e.preventDefault();
    });

/*Ajax请求商品*/
    $('.ajax-trigger').click(function () {
        $.ajax({
            type:"POST",
            dataType:"json",
            url:'test.json',
            data:{offset:6,tid:1},
            success: function(data){
                if(!data.success) return;
                var count = data.productslist.length;
                for(var tmp in data.productslist){
                    item = data.productslist[tmp];
                    var contents = "<div>"
                        +"<p class='ppic'>"
                        +"<a href='"+item.url.product+"' title='"+item.title.full+"'>"
                        +"<img src='"+item.url.pic+"' alt='"+item.title.full+"'>"
                        +"</a></p>"
                        +"<span class='newcontent'>"+item.title.abbr+"</span>"
                        +"<span class='priceandgrade'>"
                        +"<span class='pricespan'>"+item.price+"</span>"
                        +"<span><img src='http://m.shirleysdress.com/templates/m.shirleysdress.com/images/starfill.png'></span>"
                        +"<span><img src='http://m.shirleysdress.com/templates/m.shirleysdress.com/images/starfill.png'></span>"
                        +"<span><img src='http://m.shirleysdress.com/templates/m.shirleysdress.com/images/starfill.png'></span>"
                        +"<span><img src='http://m.shirleysdress.com/templates/m.shirleysdress.com/images/starfill.png'></span>"
                        +"<span><img src='http://m.shirleysdress.com/templates/m.shirleysdress.com/images/starfill.png'></span>"
                        +"<span>("+item.comment.count+")</span>"
                        +"</span></div>";
                    $(".product_show").append(contents);
                }
            }
        });
    });
