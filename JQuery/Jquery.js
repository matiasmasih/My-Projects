$(document).ready(function () {
 $('#menu').hide();
$('.menu-icon').click(function () {
    $('#menu').toggle();
});
$("#a1").click(function () {
  $("#panel1").slideToggle("slow");
});
$("#a2").click(function () {
  $("#panel2").slideToggle("slow");
});
$("#a3").click(function () {
  $("#panel3").slideToggle("slow");
});
$("#a4").click(function () { 
  $("#panel4").slideToggle("slow");
});
$("#a5").click(function () {
  $("#panel5").slideToggle("slow");
});


$('#searchInput').on('keyup', function () {
  var searchText = $(this).val().trim();
  $('.panel').each(function(){
    var output = $(this);
    if (searchText === '') {
      output.html(output.data('original-content') || output.html());
    } else {
      var content = output.html();
      output.data('original-content', content); 
      var highlightedContent = content.replace(new RegExp(searchText, 'gi'), '<span class="highlight">$&</span>');
      output.html(highlightedContent);
    }
});
});

$('.menu-icon').click(function () {
    var currentImgIndex = 0;
    var $images = $('.panel img');
    $images.eq(currentImgIndex).addClass('active');
    function changeImage() {
      $images.eq(currentImgIndex).removeClass('active');
      currentImgIndex = (currentImgIndex + 1) % $images.length;
      $images.eq(currentImgIndex).addClass('active');
    }
    setInterval(changeImage, 4000);
  });

});








