import 'dart:async';
import 'dart:io';
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:permission_handler/permission_handler.dart';
import '../services/exam_service.dart';
import '../services/mlkit_service.dart';
import '../utils/error_utils.dart';
import '../widgets/scan_overlay.dart';
import '../widgets/quick_confirm_sheet.dart';
import 'confirm_screen.dart';
import 'grade_list_screen.dart';

class ScanScreen extends StatefulWidget {
  final int classId;
  final String className;
  final int totalQuestions;
  final int maxScore;

  const ScanScreen({
    super.key,
    required this.classId,
    required this.className,
    required this.totalQuestions,
    required this.maxScore,
  });

  @override
  State<ScanScreen> createState() => _ScanScreenState();
}

class _ScanScreenState extends State<ScanScreen> with WidgetsBindingObserver {
  final _examService = ExamService();
  final _mlKitService = MLKitService();

  CameraController? _cameraController;
  List<CameraDescription>? _cameras;
  bool _cameraReady = false;
  CameraLensDirection _lensDirection = CameraLensDirection.back;

  ScanState _scanState = ScanState.idle;
  String? _lastFrameText;
  int _gradedCount = 0;
  bool _processing = false;
  bool _autoSave = true;

  DateTime _lastProcessTime = DateTime.now();
  static const Duration _processInterval = Duration(milliseconds: 800);
  bool _streaming = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadAutoSaveSetting();
    _initCamera();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _stopCamera();
    _mlKitService.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _initCamera();
    } else if (state == AppLifecycleState.paused) {
      _stopCamera();
    }
  }

  void _stopCamera() {
    _streaming = false;
    _cameraController?.stopImageStream();
    _cameraController?.dispose();
    _cameraController = null;
    _cameraReady = false;
  }

  Future<void> _loadAutoSaveSetting() async {
    const storage = FlutterSecureStorage();
    final val = await storage.read(key: 'auto_save');
    if (mounted) {
      setState(() => _autoSave = val != 'false');
    }
  }

  Future<void> _initCamera() async {
    try {
      // Android 6+ bắt buộc xin quyền camera lúc chạy; thiếu quyền thì
      // availableCameras() trả rỗng và màn hình cứ đen mãi.
      final status = await Permission.camera.request();
      if (!status.isGranted) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: const Text('Cần cấp quyền Camera để quét bài thi.'),
              action: SnackBarAction(
                label: 'Mở Cài đặt',
                onPressed: openAppSettings,
              ),
            ),
          );
        }
        return;
      }

      _cameras = await availableCameras();
      if (_cameras == null || _cameras!.isEmpty) return;
      final camera = _cameras!.firstWhere(
        (c) => c.lensDirection == _lensDirection,
        orElse: () => _cameras!.first,
      );

      _cameraController = CameraController(
        camera,
        ResolutionPreset.medium,
        enableAudio: false,
        imageFormatGroup: Platform.isAndroid
            ? ImageFormatGroup.nv21
            : ImageFormatGroup.bgra8888,
      );

      await _cameraController!.initialize();
      _startImageStream();

      if (mounted) {
        setState(() => _cameraReady = true);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Lỗi camera: $e')),
        );
      }
    }
  }

  Future<void> _toggleCamera() async {
    if (_cameras == null || _cameras!.length < 2) return;
    _stopCamera();
    setState(() {
      _cameraReady = false;
      _lensDirection = _lensDirection == CameraLensDirection.back
          ? CameraLensDirection.front
          : CameraLensDirection.back;
    });
    await _initCamera();
  }

  void _startImageStream() {
    if (_cameraController == null || _cameraController!.value.isStreamingImages) return;
    _streaming = true;
    _cameraController?.startImageStream(_processCameraImage);
  }

  void _processCameraImage(CameraImage image) {
    if (_processing || !_streaming) return;
    final now = DateTime.now();
    if (now.difference(_lastProcessTime) < _processInterval) return;
    _lastProcessTime = now;

    final camera = _cameraController?.description;
    if (camera == null) return;

    _mlKitService.recognizeCameraImage(image, camera).then((text) {
      if (!mounted || _processing || !_streaming) return;
      final stable = _mlKitService.isStableFrame(text, _lastFrameText);

      setState(() {
        if (text.trim().isNotEmpty && text.length > 10) {
          _scanState = stable ? ScanState.stable : ScanState.detecting;
          if (stable) {
            _autoCapture();
          }
        } else {
          _scanState = ScanState.idle;
        }
        _lastFrameText = text;
      });
    }).catchError((_) {});
  }

  void _autoCapture() {
    if (_processing) return;
    setState(() => _processing = true);
    _captureAndProcess();
  }

  Future<void> _captureAndProcess() async {
    try {
      _streaming = false;
      await _cameraController?.stopImageStream();

      final image = await _cameraController?.takePicture();
      if (image == null || !mounted) {
        _resetAfterCapture();
        return;
      }

      setState(() => _scanState = ScanState.processing);

      String? mlkitHint = _lastFrameText;
      try {
        final fileText = await _mlKitService.recognizeImage(image.path);
        if (fileText.trim().isNotEmpty) {
          mlkitHint = fileText;
        }
      } catch (_) {}

      final result = await _examService.extractImage(
        image.path,
        widget.classId,
        mlkitHint,
      );

      if (!mounted) {
        _resetAfterCapture();
        return;
      }
      _showQuickConfirm(result);
    } catch (e) {
      if (mounted) {
        _resetAfterCapture();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(friendlyError(e))),
        );
      }
    }
  }

  void _resetAfterCapture() {
    _processing = false;
    _scanState = ScanState.idle;
    _lastFrameText = null;
    if (_cameraController != null && _cameraController!.value.isInitialized) {
      _startImageStream();
    }
  }

  void _showQuickConfirm(result) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => QuickConfirmSheet(
        result: result,
        classId: widget.classId,
        totalQuestions: widget.totalQuestions,
        maxScore: widget.maxScore,
        autoSaveEnabled: _autoSave,
        onSaved: () {
          setState(() {
            _gradedCount++;
          });
          _resetAfterCapture();
          Navigator.pop(ctx);
        },
        onEditDetail: () {
          _processing = false;
          Navigator.pop(ctx);
          _stopCamera();
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => ConfirmScreen(
                extractResult: result,
                classId: widget.classId,
                totalQuestions: widget.totalQuestions,
                maxScore: widget.maxScore,
                onSaved: () {
                  setState(() {
                    _gradedCount++;
                  });
                },
              ),
            ),
          ).then((_) => _initCamera());
        },
      ),
    ).whenComplete(() {
      if (_processing) {
        _resetAfterCapture();
      }
    });
  }

  Future<void> _manualCapture() async {
    if (_cameraReady && !_processing) {
      setState(() => _processing = true);
      await _captureAndProcess();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        title: Text(widget.className),
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        actions: [
          if (_cameras != null && _cameras!.length > 1)
            IconButton(
              icon: const Icon(Icons.cameraswitch),
              tooltip: 'Đổi camera trước/sau',
              onPressed: _cameraReady ? _toggleCamera : null,
            ),
          IconButton(
            icon: const Icon(Icons.list_alt),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => const GradeListScreen(),
                ),
              );
            },
          ),
        ],
      ),
      body: _cameraReady && _cameraController != null
          ? Stack(
              children: [
                CameraPreview(_cameraController!),
                ScanOverlay(
                  state: _scanState,
                  gradedCount: _gradedCount,
                ),
                Positioned(
                  bottom: 40,
                  left: 0,
                  right: 0,
                  child: Center(
                    child: _processing
                        ? const CircularProgressIndicator(color: Colors.white)
                        : FloatingActionButton.large(
                            onPressed: _manualCapture,
                            backgroundColor: Colors.white,
                            child: const Icon(
                              Icons.camera_alt,
                              color: Colors.black,
                              size: 36,
                            ),
                          ),
                  ),
                ),
              ],
            )
          : const Center(child: CircularProgressIndicator(color: Colors.white)),
    );
  }
}
