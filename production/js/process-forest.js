function showModal(header, body){
  $('.modal').modal();
  $("#modal-header").html(header);
  $("#modal-body").html(body);
  $('.modal').modal('open');
}
function hideModal(){
  $('.modal').modal('close');
}
paragraphs = [`Subject:- PLACEMENT POLICY.  
In order to utilize the expertise of the officers who have received foreign training in various fields, the provincial Government has decided to adopt the Placement Policy, approved by the Prime Minister of Pakistan, and make it a part of its Posting/Transfer Policy. Placement Policy is as follows:- 
i) All placements would be made on the basis of merit and keeping in view the needs of the organization. 
ii) The first priority in placement must go the parent organization of the participant from where the individual had applied. This will be in consonance with the concept of establishing the Need for the department and fulfilling the need through capacity building for the organization. 
iii) In order to follow the bottom up approach for Devolution, the priority within departments must go to the Districts, the Provinces and than the Federal Government. iv) The second priority in placement should go to up-grading the existing training Institution within the country. The knowledge gained by the officers, will be of immense value to bring about a qualitative change in the training institutions. The following proposals are made in this regard: 
a) Permanent posting of an officer to the training institutions for 2-3 years;
b) Temporary attachment with the training intuitions for 3 to 6 months for some research project on helping in developing case studies; 
c) Earmarked as a visiting faculty member for specific subject. 
v) Individuals posted to their parent organizations will also organize training for their subordinates within the department, in order to transfer the knowledge and bring about a qualitative change internally; 
vi) The Normal tenure of posting as already provided in the policy would be ensured; 
vii) No participant should be allowed to be posted on deputation to multinational donor agencies for at least 5 years; 
viii) No participants will decline/represent against his/her posting.`];

TypingTestComponent = Vue.component('typing-test-template',{
  template: "#typing-test-template",
  data: function(){
    return {
      loading: false,
      userLogin: null,
      typedWord: "",
      typedWords: [],
      paragraphHTML: "",
      processed_words: "",
      unprocessed_words: "",
      paragraph: paragraphs[Math.floor(Math.random() * paragraphs.length)].replace(/\n|\r/g, " ").replace(/ +(?= )/g,''),
      correct: 0,
      incorrect: 0,
      character_pressed: 0,
      first: true,
      time: 3,
      remaining_time: 0,
      passed_time: 0,
      wpm: 0,
      prevScrollOffset: 0,
      result: {
        announced: false,
        correct_words: [],
        incorrect_words: [],
        total_characters: 0,
        correct_characters: 0,
        incorrect_characters: 0,
        accuracy: 0,
        wpm: 0
      }
    }

  },
  watch: {
    typedWord: function () {
      if(this.typedWord[this.typedWord.length - 1] == " "){
        this.typedWords.push(this.typedWord.replace(" ", ""));
        this.typedWord = "";
        var typed_array = this.typedWords;
        if(typed_array.length <= this.unprocessed_words.length){
          var typed_word = typed_array[typed_array.length-1];
          this.character_pressed += typed_word.length+1;

          if(typed_word == this.unprocessed_words[typed_array.length -1]){
            this.correct += 1;
            this.result.correct_words.push(typed_word);
            if(typed_array.length < this.unprocessed_words.length){
              var next_word = "<span id='spangrey' style='background:#88bdff'>"+this.unprocessed_words[typed_array.length]+"</span>";
              this.processed_words[typed_array.length] = next_word;
            }
            var word = "<span style='color:green'>"+this.unprocessed_words[typed_array.length -1]+"</span>";
            this.processed_words[typed_array.length-1] = word;
            this.paragraphHTML = this.processed_words.join(" ");
          }else{
            this.incorrect += 1;
            this.result.incorrect_words.push(typed_word);
            if(typed_array.length < this.unprocessed_words.length){
              var next_word = "<span id='spangrey' style='background:#88bdff'>"+this.unprocessed_words[typed_array.length]+"</span>";
              this.processed_words[typed_array.length] = next_word;
            }
            var word = "<span  style='color:red'>"+this.unprocessed_words[typed_array.length -1]+"</span>";
            this.processed_words[typed_array.length-1] = word;
            this.paragraphHTML = this.processed_words.join(" ");
          }

          var containerOffset = Math.floor($(".typing-paragraph").offset().top + $(".typing-paragraph").height());
          if($("#spangrey").offset().top >= containerOffset - 100){
            $(".typing-paragraph").animate({scrollTop: document.getElementsByClassName("typing-paragraph")[0].scrollTop + 72},300);
          }
        }
      }
    }
  },
  methods: {
    getLogin: function(){
      this.userLogin = localStorage.getItem("_logindetails") ? JSON.parse(localStorage.getItem("_logindetails")) : null;

      return localStorage.getItem("_logindetails") ? localStorage.getItem("_logindetails") : false;
      
    },
    saveResult: function(){
      this.loading = true;
      if(this.getLogin()
          && JSON.parse(this.getLogin()).tested == 0
          || JSON.parse(this.getLogin()).allow_retest == 1
        ){
        this.result.total_words_typed = this.result.correct_words.join(" ")+this.result.incorrect_words.join(" ");
        this.result.time = this.time * 60;
        this.result.time = (Math.floor(this.result.time/60).toFixed())+":"+this.pad((this.result.time % 60).toFixed());
        this.result.uid = JSON.parse(this.getLogin()).id;
		this.result.roll_no = JSON.parse(this.getLogin()).roll_no;
        this.$http.post("./api_backend/save_result.php", JSON.stringify({result: this.result}))
            .then(response => {
              this.loading = false;
              localStorage.removeItem("_logindetails");
        });
      }
    },
    pad: function(d){
      return (d < 10) ? '0' + d.toString() : d.toString();
    },
    highlightFirstWord: function(){
      var next_word = "<span id='spangrey' style='background:#88bdff'>"+this.unprocessed_words[this.typedWords.length]+"</span>";
      this.processed_words[this.typedWords.length] = next_word;
    },
    init: function () {
      if(this.first){
        if(this.typedWord.length != 0){
          this.first = false;
          _this = this;

          var intr = setInterval(function(){
            _this.remaining_time = _this.remaining_time - 1;
            _this.passed_time = (_this.time * 60) - _this.remaining_time;
            _this.result.correct_characters = _this.result.correct_words.join(" ").length;
            _this.wpm = Math.floor(((_this.character_pressed / 5) - _this.result.incorrect_words.length) / (_this.passed_time / 60));
            _this.wpm = _this.wpm < 0 ? 0 : _this.wpm;
            _this.result.total_characters = _this.character_pressed;
            _this.result.correct_characters = _this.result.correct_words.join(" ").length;
            _this.result.incorrect_characters = _this.result.incorrect_words.join(" ").length;
            _this.result.wpm = Math.floor(((_this.character_pressed / 5) - _this.result.incorrect_words.length) / _this.time);
            _this.result.wpm = _this.result.wpm < 0 ? 0 : _this.result.wpm;
            _this.result.accuracy = Math.floor((_this.result.correct_characters / _this.result.total_characters)*100);
            if(_this.remaining_time <= 0) {
              clearInterval(intr);
              _this.result.announced = true;
              _this.saveResult();
            }
          }, 1000);
        }
      }
    }
  },
  mounted: function(){
    if(!this.getLogin())
      this.$router.push("/login");
    $("body").removeClass("grey lighten-4");
    $("body").addClass("grey lighten-4");
    this.remaining_time = this.time * 60;
    this.processed_words = this.paragraph.split(" ");
    this.unprocessed_words = this.paragraph.split(" ");
    this.highlightFirstWord();
    this.paragraphHTML = this.processed_words.join(" ");
    $("input[type='text']").on('cut copy paste drop', function () {
      return false;
    });
    setTimeout(function(){
      if($(".typing-paragraph").height() > 300){
        $(".typing-paragraph").css({overflowY: "scroll", height: "300px"});
      }
    },1);
    $(window).bind('beforeunload', function(){
      return 'Are You Sure You Want To Leave This Site? You Can Be Disqualified !';
    });
  }
});

LoginComponent = Vue.component('login-template', {
  template: "#login-template",
  data: function(){
    return {
      email: '',
      password: '',
      loading: false
    }
  },
  methods:{
    getLogin: function(){
      return localStorage.getItem("_logindetails") ? localStorage.getItem("_logindetails") : false;
    },
    login: function(){
      this.loading = true;
      if(this.email == "" || this.password == ""){
        showModal("Wait !", "Please Fill In The Form Correctly !");
        this.loading = false;
      }else if(isNaN(this.email) || this.email.length != 13){
        showModal("Wait !", "The Cnic You Entered Is Not Valid !");
        this.loading = false;
      }else if(isNaN(this.password)){
         showModal("Wait !", "The Roll Number You Entered Is Not Valid !");
      }else {
        this.$http.post("./api_backend/login.php", JSON.stringify({cnic: this.email, rollno: this.password}))
            .then(response => {
            this.loading = false;
          if(response.body == "notfound"){
            showModal("NOT TODAY !", "No User Name Exists With Email And Password You Provided");
          }else{
            var userlogin = response.body.user;
            if((userlogin.tested == '0' && userlogin.logged_once == '0') || userlogin.allow_retest == '1') {
              localStorage.setItem("_logindetails", JSON.stringify(response.body.user));
              this.$router.push("/typing-test");
            }else{
              showModal("Can't Login !", "You're already being tested");
            }
          }
        });
      }
    }
  },
  mounted: function(){
    if(this.getLogin()){
      this.$router.push("/typing-test");
    }
    $("body").removeClass("grey lighten-4");
    $("body").addClass("grey lighten-4");
    $(window).unbind('beforeunload');
  }
});

var router = new VueRouter(
    {
        mode: 'history',
        routes: [
          {path: '', component: LoginComponent, name: "home"},
          {path: '/typing-test', component: TypingTestComponent, name: "typing",
              beforeEnter: (to, from, next) => {
                if(!from.name){
                    localStorage.removeItem("_logindetails");
                    next("/");
                }else{
                    next();
                }
              }
          },
          {path: '/login', component: LoginComponent, name: "login"}
        ]
    }
);

var app = new Vue({
    el: '#typing-application',
    router: router
});